<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ImportTranslationService;
use AnyMedia\Interpresso\Services\ExportTranslationService;
use AnyMedia\Interpresso\Services\ProcessLock;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ForceExportTranslationJob;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ProcessLockTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    #[Test]
    public function concurrent_conditional_updates_have_exactly_one_winner(): void
    {
        // The normal application stays on in-memory SQLite. Only the two child
        // connections share this disposable database for real SQL contention.
        $path = tempnam(sys_get_temp_dir(), 'interpresso-lock-');
        $db = new \PDO('sqlite:' . $path);
        $db->exec('CREATE TABLE lock_settings (id INTEGER PRIMARY KEY, process_running BOOLEAN NOT NULL DEFAULT 0,
            process_owner VARCHAR(255), process_started_at TIMESTAMP, process_expires_at TIMESTAMP)');
        $db->exec('INSERT INTO lock_settings (id) VALUES (1)');
        $children = [];
        try {
            foreach (['cron-host:111 import', 'web-host:222 export'] as $owner) {
                $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/process-lock-contender.php', $path, $owner],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                stream_set_timeout($pipes[1], 10);
                $children[] = [$process, $pipes];
            }
            foreach ($children as [, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
            foreach ($children as [, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($children as [, $pipes]) {
                $results[] = json_decode(fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('', stream_get_contents($pipes[2]));
            }
            $this->assertSame(1, count(array_filter(array_column($results, 'won'))));
            foreach ($results as $result) {
                // A read/check before UPDATE would fail this assertion, even if
                // one contender happened to be scheduled ahead of the other.
                $this->assertCount(1, $result['queries']);
                $sql = strtolower($result['queries'][0]['query']);
                $this->assertStringStartsWith('update ', $sql);
                $this->assertStringContainsString('"process_running" = ? or "process_expires_at" is null or "process_expires_at" < ?', $sql);
            }
            $winner = $db->query('SELECT process_owner FROM lock_settings')->fetchColumn();
            $this->assertSame($results[0]['won'] ? 'cron-host:111 import' : 'web-host:222 export', $winner);
        } finally {
            foreach ($children as [$process, $pipes]) {
                foreach ($pipes as $pipe) fclose($pipe);
                proc_terminate($process);
                proc_close($process);
            }
            unset($db);
            unlink($path);
        }
    }

    #[Test]
    #[DataProvider('lockStates')]
    public function only_a_running_unexpired_lease_blocks(bool $running, ?int $expiresIn, bool $expected): void
    {
        $this->freezeSecond();
        Setting::getCached(); // Deliberately prime the old unlocked cache.
        Setting::query()->update(['process_running' => $running, 'process_owner' => 'cron:123 import',
            'process_started_at' => now()->subMinutes(5),
            'process_expires_at' => $expiresIn === null ? null : now()->addSeconds($expiresIn)]);
        $lock = new ProcessLock();
        $this->assertSame($expected, $lock->isLocked());
        $this->assertSame($running, $lock->current() !== null);
        $this->assertSame(!$expected, $lock->acquire('web:456 export', 900));
        if (!$expected) {
            $this->assertSame('web:456 export', $lock->current()['owner']);
            $lock->release();
            $this->assertCleared();
        }
    }

    public static function lockStates(): array
    {
        return ['idle' => [false, 900, false], 'legacy flag' => [true, null, false],
            'expired' => [true, -1, false], 'live' => [true, 900, true], 'expiry boundary' => [true, 0, true]];
    }

    #[Test]
    public function heartbeat_extends_expiry_and_unowned_or_obsolete_handles_cannot_release_the_successor(): void
    {
        $this->freezeSecond();
        $old = new ProcessLock();
        $this->assertTrue($old->acquire('cron:123 import', 60));
        $started = $old->current()['started_at'];
        $this->travel(30)->seconds();
        $old->refresh();
        $this->assertSame(now()->addSeconds(60)->toIso8601String(), $old->current()['expires_at']);
        $this->assertSame($started, $old->current()['started_at']);
        (new ProcessLock())->release();
        $this->assertTrue($old->isLocked());
        $this->travel(61)->seconds();
        $replacement = new ProcessLock();
        $this->assertTrue($replacement->acquire('web:456 export', 60));
        try {
            $old->refresh();
            $this->fail('An expired owner cannot heartbeat a replacement.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('expired or was replaced', $error->getMessage());
        }
        $old->release();
        $old->release();
        $this->assertTrue($replacement->isLocked());
        $this->assertSame('web:456 export', $replacement->current()['owner']);
        $replacement->release();
        $this->assertCleared();
    }

    #[Test]
    public function a_failed_acquire_on_the_same_handle_keeps_its_existing_ownership(): void
    {
        $lock = new ProcessLock();
        $this->assertTrue($lock->acquire('cron:123 import', 900));
        $this->assertFalse($lock->acquire('cron:123 export', 900));
        $this->assertSame('cron:123 import', $lock->current()['owner']);
        $lock->release();
        $this->assertCleared();
    }

    #[Test]
    public function expired_only_unlock_cannot_clear_a_new_acquisition_at_the_update_boundary(): void
    {
        $lock = new ProcessLock();
        $lock->acquire('crashed:123 import', 1);
        $this->travel(2)->seconds();
        $replacement = new ProcessLock();
        $intercepted = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$intercepted, $replacement): void {
            if (!$intercepted && str_starts_with(strtolower($sql), 'update ')) {
                $intercepted = true;
                $this->assertTrue($replacement->acquire('cron:456 export', 900));
            }
        });
        $this->assertFalse((new ProcessLock())->releaseExpired());
        $this->assertTrue($replacement->isLocked());
        $this->assertSame('cron:456 export', $replacement->current()['owner']);
        $replacement->release();
        $this->assertCleared();
    }

    #[Test]
    #[DataProvider('commands')]
    public function every_command_refuses_a_cron_lease_without_any_queued_jobs(string $command): void
    {
        $lock = new ProcessLock();
        $lock->acquire('cron-host:123 import translations', 900);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('job_batches', 0);
        $this->artisan('interpresso:' . $command)
            ->expectsOutput('Another process is running: ' . $lock->description() . '.')->assertExitCode(0);
        $this->assertTrue($lock->isLocked());
        $this->assertSame('cron-host:123 import translations', $lock->current()['owner']);
    }

    public static function commands(): array
    {
        return array_map(fn ($command) => [$command], ['import-languages', 'import-translations', 'find-missing-translations',
            'export-translations', 'export-translations-deployment', 'developer-download', 'prune-batches',
            'send-automatic-pending-translations-notification']);
    }

    #[Test]
    public function a_command_holds_a_lease_during_work_and_releases_it_after_a_throwable(): void
    {
        $this->mock(ImportTranslationService::class)->shouldReceive('importTranslations')->once()
            ->andReturnUsing(function (): void {
                $lock = new ProcessLock();
                $this->assertTrue($lock->isLocked());
                $this->assertStringContainsString('interpresso:import-translations', $lock->current()['owner']);
                throw new \TypeError('Injected command error');
            });
        try {
            $this->artisan('interpresso:import-translations')->run();
            $this->fail('The command must propagate the error.');
        } catch (\TypeError $error) {
            $this->assertSame('Injected command error', $error->getMessage());
        }
        $this->assertCleared();
    }

    #[Test]
    public function developer_download_rolls_back_a_php_error_before_releasing_its_lease(): void
    {
        // Only the MySQL-specific FK switches are stubbed. Transactions, lock
        // updates and the command itself use the real in-memory connection.
        $connection = DB::connection('testbench');
        $level = $connection->transactionLevel();
        $proxy = \Mockery::mock($connection);
        $proxy->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS=0;')->once()->andReturnTrue();
        $proxy->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS=1;')->once()->andReturnTrue();
        $manager = \Mockery::mock(DB::getFacadeRoot());
        $manager->shouldReceive('connection')->with('testbench')->andReturn($proxy);
        DB::swap($manager);
        Http::fake(static fn () => throw new \TypeError('Download failed inside transaction'));
        try {
            $this->artisan('interpresso:developer-download')->run();
            $this->fail('The download must propagate the PHP error.');
        } catch (\TypeError $error) {
            $this->assertSame('Download failed inside transaction', $error->getMessage());
        }
        $this->assertSame($level, $connection->transactionLevel());
        $this->assertCleared();
    }

    #[Test]
    public function unlock_requires_force_for_a_live_lease_and_reports_its_owner_and_start(): void
    {
        $lock = new ProcessLock();
        $lock->acquire('cron:999 import', 900);
        $this->artisan('interpresso:unlock')->expectsOutput('Process lock: ' . $lock->description() . '.')
            ->expectsOutput('The lock is still active. Use --force to release it.')->assertExitCode(1);
        $this->assertTrue($lock->isLocked());
        $this->artisan('interpresso:unlock', ['--force' => true])
            ->expectsOutput('Process lock: ' . $lock->description() . '.')
            ->expectsOutput('Process lock released.')->assertExitCode(0);
        $this->assertCleared();
    }

    #[Test]
    public function unlock_clears_expired_and_legacy_leases_and_is_idempotent(): void
    {
        foreach ([now()->subSecond(), null] as $expiresAt) {
            Setting::query()->update(['process_running' => true, 'process_owner' => 'crashed:123 import',
                'process_started_at' => now()->subHour(), 'process_expires_at' => $expiresAt]);
            $this->artisan('interpresso:unlock')->assertExitCode(0);
            $this->assertCleared();
        }
        $this->artisan('interpresso:unlock')->assertExitCode(0);
    }

    #[Test]
    public function queued_ui_batches_refuse_a_live_cron_lease_and_allow_an_expired_one(): void
    {
        $this->seedBrowserScenario('bulk');
        $lock = new ProcessLock();
        $lock->acquire('cron-host:567 import', 1);
        $this->useDatabaseQueue();
        foreach (['import-languages', 'import-translations', 'find-missing', 'approve', 'export'] as $action) {
            $this->post(route('interpresso.languages.' . $action))->assertRedirect()
                ->assertSessionHas('toast.type', 'WARNING')
                ->assertSessionHas('toast.message', __('interpresso::global.import.processing_no_action') . ' ' . $lock->description() . '.');
        }
        $language = Language::where('code', 'en')->firstOrFail();
        foreach (['approve-all', 'export'] as $action) {
            $this->post(route('interpresso.translations.' . $action, $language))->assertRedirect()
                ->assertSessionHas('toast.type', 'WARNING');
        }
        $this->assertDatabaseCount('job_batches', 0);
        $this->travel(2)->seconds();
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $this->assertDatabaseCount('job_batches', 1);
        $this->runWorker();
        $this->assertCleared();
    }

    #[Test]
    public function api_reports_expiry_and_rejects_force_export_during_a_local_cron_run(): void
    {
        $this->useDatabaseQueue();
        config(['interpresso.api_shared_api_key' => 'lock-test-key']);
        $lock = new ProcessLock();
        $lock->acquire('cron:123 import', 60);
        $payload = ['api_key' => 'lock-test-key'];
        $this->postJson(route('interpresso.api.jobs-running'), $payload)->assertOk()
            ->assertJsonPath('process_running', true)->assertJsonPath('queue_running', false)
            ->assertJsonPath('process_owner', 'cron:123 import')
            ->assertJsonPath('process_started_at', $lock->current()['started_at'])
            ->assertJsonPath('process_expires_at', $lock->current()['expires_at']);
        $this->postJson(route('interpresso.api.force-export'), $payload)->assertStatus(409)
            ->assertJsonPath('lock.owner', 'cron:123 import');
        $this->assertDatabaseCount('job_batches', 0);
        $this->travel(61)->seconds();
        $this->postJson(route('interpresso.api.jobs-running'), $payload)->assertOk()->assertJsonPath('process_running', false);
    }

    #[Test]
    public function remote_expired_leases_do_not_block_local_commands(): void
    {
        Setting::firstOrFail()->update(['enable_multi_host' => true, 'domains' => 'https://peer.example']);
        Setting::getFreshCached();
        Http::fake(['*' => Http::response(['process_running' => true, 'process_owner' => 'peer:123 import',
            'process_started_at' => now()->subHour()->toIso8601String(), 'process_expires_at' => now()->subSecond()->toIso8601String()])]);
        $this->artisan('interpresso:find-missing-translations')->expectsOutput('Everything up to date.')->assertExitCode(0);
        $this->assertCleared();
    }

    #[Test]
    public function cancelled_batch_callbacks_and_cancel_requests_cannot_clear_a_new_cron_lock(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $this->post(route('interpresso.translations.approve-all', Language::where('code', 'en')->firstOrFail()))->assertRedirect();
        $reserved = Queue::connection('database')->pop(config('interpresso.queue_name'));
        $this->post(route('interpresso.languages.cancel-jobs'))->assertRedirect();
        $this->assertCleared();
        $lock = new ProcessLock();
        $lock->acquire('cron:456 replacement', 900);
        $reserved->fire();
        $this->post(route('interpresso.languages.cancel-jobs'))->assertRedirect();
        $this->assertTrue($lock->isLocked());
        $this->assertSame('cron:456 replacement', $lock->current()['owner']);
    }

    #[Test]
    public function failure_while_preparing_a_batch_releases_all_lock_columns(): void
    {
        $this->useDatabaseQueue();
        $this->actingAs(Translator::firstOrFail());
        Bus::shouldReceive('batch')->once()->andThrow(new \TypeError('Cannot prepare batch'));
        $this->post(route('interpresso.languages.import-languages'))->assertStatus(500);
        $this->assertCleared();
    }

    #[Test]
    public function obsolete_queued_jobs_cannot_write_under_a_replacement_lease(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        config(['interpresso.process_lock_ttl' => 1]);
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $this->travel(2)->seconds();
        $replacement = new ProcessLock();
        $this->assertTrue($replacement->acquire('cron:456 replacement', 900));
        $before = \AnyMedia\Interpresso\Models\Translation::orderBy('id')->get()->toArray();
        $this->runWorker();
        $this->assertSame($before, \AnyMedia\Interpresso\Models\Translation::orderBy('id')->get()->toArray());
        $this->assertTrue($replacement->isLocked());
        $this->assertSame('cron:456 replacement', $replacement->current()['owner']);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    #[Test]
    public function cli_sync_batch_failure_releases_the_lease(): void
    {
        $this->mock(ExportTranslationService::class)->shouldReceive('forceExportTranslationForLanguage')->once()
            ->andThrow(new \TypeError('CLI export failed'));
        try {
            resolve(BatchProcessor::class)->dispatch([new ForceExportTranslationJob(Language::firstOrFail())]);
            $this->fail('The CLI failure must propagate.');
        } catch (\TypeError $error) {
            $this->assertSame('CLI export failed', $error->getMessage());
        }
        $this->assertCleared();
    }

    #[Test]
    public function migration_handles_partial_custom_tables_and_rolls_back_without_locking_existing_settings(): void
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000000_add_process_lock_to_settings_table.php';
        $original = config('interpresso.table_settings');
        config(['interpresso.table_settings' => 'partial_lock_settings']);
        try {
            $migration->up(); // Missing table is harmless.
            $migration->down();
            Schema::create('partial_lock_settings', function ($table): void {
                $table->id();
                $table->boolean('process_running')->default(false);
                $table->string('process_owner')->nullable();
            });
            DB::table('partial_lock_settings')->insert(['process_running' => true]);
            $migration->up();
            $migration->up();
            $row = DB::table('partial_lock_settings')->first();
            $this->assertNull($row->process_owner);
            $this->assertNull($row->process_started_at);
            $this->assertNull($row->process_expires_at);
            $this->assertFalse((new ProcessLock())->isLocked());
            $migration->down();
            $migration->down();
            $this->assertFalse(Schema::hasColumn('partial_lock_settings', 'process_owner'));
            $this->assertTrue(Schema::hasColumn('partial_lock_settings', 'process_running'));
            $migration->up();
            $this->assertTrue(Schema::hasColumns('partial_lock_settings', ['process_owner', 'process_started_at', 'process_expires_at']));
        } finally {
            config(['interpresso.table_settings' => $original]);
            Schema::dropIfExists('partial_lock_settings');
        }
    }

    private function assertCleared(): void
    {
        $setting = Setting::firstOrFail();
        $this->assertFalse($setting->process_running);
        $this->assertNull($setting->process_owner);
        $this->assertNull($setting->process_started_at);
        $this->assertNull($setting->process_expires_at);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertNull((new ProcessLock())->current());
    }
}
