<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\PendingTranslationsNotification;
use AnyMedia\Interpresso\Services\ExportTranslationService;
use AnyMedia\Interpresso\Services\ImportLanguageService;
use AnyMedia\Interpresso\Services\ImportTranslationService;
use AnyMedia\Interpresso\Services\MissingTranslationService;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;

class ConsoleCommandsTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    public function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'queue.connections.sync.driver' => 'sync']);
    }

    #[Test]
    #[DataProvider('approvalScopes')]
    public function approval_runs_inline_in_cli_under_sync_and_records_the_administrator(bool $oneLanguage): void
    {
        $this->seedBrowserScenario('bulk');
        $admin = Translator::where('admin', true)->firstOrFail();
        $options = ['--translator' => (string) $admin->id] + ($oneLanguage ? ['--language' => 'en'] : []);
        $this->artisan('interpresso:approve-translations', $options)
            ->expectsOutput('Total translations approved: ' . ($oneLanguage ? 3 : 4) . '.')->assertExitCode(0);
        $this->assertSame($oneLanguage ? 1 : 0, Translation::where('approved', false)->count());
        $this->assertSame($oneLanguage ? 3 : 4, Translation::where('approved_by', $admin->id)->count());
        $this->assertSame(0, Translation::where('language_code', 'en')->where('approved', false)->count());
        $this->assertDatabaseCount('job_batches', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertFalse(Setting::getCached()->process_running);
    }

    public static function approvalScopes(): array
    {
        return [[true], [false]];
    }

    #[Test]
    #[DataProvider('invalidApprovalOptions')]
    public function approval_rejects_invalid_attribution_and_scope_without_writes(array $options): void
    {
        $this->seedBrowserScenario('bulk');
        $before = Translation::orderBy('id')->get()->toArray();
        $this->artisan('interpresso:approve-translations', $options)->assertExitCode(1);
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertFalse(Setting::getCached()->process_running);
    }

    public static function invalidApprovalOptions(): array
    {
        return [
            'missing administrator' => [[]],
            'non administrator' => [['--translator' => '2']],
            'unknown administrator' => [['--translator' => '99999']],
            'invalid administrator' => [['--translator' => '1x']],
            'unknown language' => [['--translator' => '1', '--language' => 'unknown']],
        ];
    }

    #[Test]
    public function approval_command_releases_its_lease_when_the_service_fails(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->mock(\AnyMedia\Interpresso\Services\ApproveLanguagesService::class)->shouldReceive('approveLanguages')->once()
            ->andThrow(new \TypeError('CLI approval failed'));
        try {
            $this->artisan('interpresso:approve-translations', ['--translator' => '1'])->run();
            $this->fail('The command must propagate the failure.');
        } catch (\TypeError $error) {
            $this->assertSame('CLI approval failed', $error->getMessage());
        }
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    #[DataProvider('forceOptions')]
    public function language_export_under_sync_preserves_other_languages(array $options, bool $force): void
    {
        $this->seedBrowserScenario('bulk');
        Setting::firstOrFail()->update(['db_loader' => false]);
        Setting::getFreshCached();
        Translation::where('language_code', 'de')->update(['approved' => true]);
        $german = Translation::where('language_code', 'de')->get()->toArray();
        $this->artisan('interpresso:export-translations', $options + ['--language' => 'en'])->assertExitCode(0);
        $this->assertTrue(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertSame($german, Translation::where('language_code', 'de')->get()->toArray());
        $this->assertFileDoesNotExist(app()->langPath('de/e2e.php'));
        $this->assertSame($force, File::exists(app()->langPath('en/e2e.php')));
        $this->assertDatabaseCount('job_batches', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[Test]
    public function model_only_language_export_under_sync_preserves_files_and_other_model_locales(): void
    {
        $this->seedBrowserScenario('models');
        Setting::firstOrFail()->update(['db_loader' => false]);
        Setting::getFreshCached();
        $this->artisan('interpresso:export-translations', ['--language' => 'en', '--only-models' => true])->assertExitCode(0);
        $this->assertSame(['en' => 'Model English', 'de' => 'Old German'], json_decode(DB::table('e2e_articles')->value('title'), true));
        $this->assertFalse(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertFileDoesNotExist(app()->langPath('vendor/e2e-vendor/en/e2e.php'));
        $this->assertDatabaseCount('jobs', 0);
    }

    #[Test]
    public function export_rejects_an_unknown_language_instead_of_exporting_all_languages(): void
    {
        $this->seedBrowserScenario('bulk');
        $before = Translation::orderBy('id')->get()->toArray();
        $this->artisan('interpresso:export-translations', ['--language' => 'unknown'])->assertExitCode(1);
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    public function import_languages_discovers_directories_not_json_only_locales_and_notifies_admins(): void
    {
        $this->seedBrowserScenario('imports');
        File::put(app()->langPath('fr.json'), '{"hello":"Bonjour"}');
        $this->artisan('interpresso:import-languages')->expectsOutput('New languages imported: Italian.')->assertExitCode(0);
        $this->assertEqualsCanonicalizing(['en', 'de', 'it'], Language::pluck('code')->all());
        $this->assertSame(6, Translation::count());
        $this->assertAdminMessages([__('interpresso::languages.import_languages_success', ['languages' => 'Italian']) . __('interpresso::global.reload_suggestion')]);
        $this->artisan('interpresso:import-languages')->expectsOutput('Nothing imported.')->assertExitCode(0);
        $this->assertSame(3, Language::count());
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    public function import_translations_persists_sources_without_replacing_existing_values(): void
    {
        $this->seedBrowserScenario('imports');
        $this->artisan('interpresso:import-translations')->expectsOutput('New translations imported: 2.')->assertExitCode(0);
        $this->assertDatabaseHas(config('interpresso.table_translations'), ['key' => 'imported', 'value' => 'Imported from a real PHP file', 'approved' => true, 'exported' => true]);
        $this->assertDatabaseHas(config('interpresso.table_translations'), ['key' => 'Imported JSON key', 'value' => 'Imported from a real JSON file']);
        File::put(app()->langPath('en/browser.php'), "<?php return ['imported' => 'Replacement must be skipped'];");
        $this->artisan('interpresso:import-translations')->expectsOutput('New translations imported: 0.')->assertExitCode(0);
        $this->assertSame('Imported from a real PHP file', Translation::where('key', 'imported')->value('value'));
        $this->assertSame(8, Translation::count());
        // The manual explicitly documents no CLI import result notification.
        $this->assertDatabaseCount('notifications', 0);
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    public function find_missing_creates_root_counterparts_and_reports_when_counts_match(): void
    {
        $this->seedBrowserScenario();
        $this->artisan('interpresso:find-missing-translations')->expectsOutput('New missing translations created: 6.')->assertExitCode(0);
        $this->assertSame(6, Translation::where('language_code', 'de')->where('approved', false)->where('needs_translation', true)->where('exported', false)->count());
        $this->assertEqualsCanonicalizing(Translation::where('language_code', 'en')->pluck('shared_identifier')->all(), Translation::where('language_code', 'de')->pluck('shared_identifier')->all());
        $this->assertAdminMessages([__('interpresso::languages.find_missing_translations_success', ['total' => 0, 'language_code' => 'en']) . __('interpresso::global.reload_suggestion')]);
        $this->artisan('interpresso:find-missing-translations')->expectsOutput('Everything up to date.')->assertExitCode(0);
        $this->assertSame(12, Translation::count());
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    #[DataProvider('forceOptions')]
    public function export_obeys_force_approval_and_updated_flags_and_writes_files(array $options, bool $force): void
    {
        $this->seedBrowserScenario();
        Setting::firstOrFail()->update(['db_loader' => false]);
        Setting::getFreshCached();
        $before = Translation::where('approved', false)->get()->toArray();
        $this->artisan('interpresso:export-translations', $options)->expectsOutput('Total translations exported: ' . ($force ? 3 : 1) . '.')->assertExitCode(0);
        $vendor = require app()->langPath('vendor/e2e-vendor/en/e2e.php');
        $this->assertSame('Vendor notice', $vendor['vendor_notice']);
        $this->assertArrayNotHasKey('vendor_pending', $vendor);
        $this->assertSame($force, isset($vendor['vendor_ready']));
        if ($force) {
            $this->assertSame(['welcome' => 'Welcome home'], require app()->langPath('en/e2e.php'));
        } else {
            $this->assertFileDoesNotExist(app()->langPath('en/e2e.php'));
        }
        $this->assertTrue(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertSame($before, Translation::where('approved', false)->get()->toArray());
        $this->assertAdminMessages([__('interpresso::translations.export_languages_success', ['languages' => 'English', 'total' => $force ? 3 : 1]) . __('interpresso::global.reload_suggestion')]);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertDatabaseCount('job_batches', 0);
    }

    public static function forceOptions(): array
    {
        return ['omitted' => [[], false], 'zero' => [['--force' => '0'], false], 'one' => [['--force' => '1'], true]];
    }

    #[Test]
    public function db_mode_exports_only_models_including_forced_rewrites(): void
    {
        $this->seedBrowserScenario('models');
        Setting::firstOrFail()->update(['db_loader' => true]);
        Setting::getFreshCached();
        $this->artisan('interpresso:export-translations')->assertExitCode(0);
        $this->assertSame(['en' => 'Model English', 'de' => 'Model German'], json_decode(DB::table('e2e_articles')->value('title'), true));
        DB::table('e2e_articles')->update(['title' => '{"en":"Overwritten","de":"Overwritten"}']);
        $this->artisan('interpresso:export-translations', ['--force' => '1'])->assertExitCode(0);
        $this->assertSame(['en' => 'Model English', 'de' => 'Model German'], json_decode(DB::table('e2e_articles')->value('title'), true));
        $this->assertSame(2, Translation::where('type', 'model')->where('exported', true)->count());
        $this->assertFalse(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertFileDoesNotExist(app()->langPath('vendor/e2e-vendor/en/e2e.php'));
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    public function deployment_forces_files_and_models_even_in_db_mode(): void
    {
        $this->seedBrowserScenario('models');
        Setting::firstOrFail()->update(['db_loader' => true]);
        Setting::getFreshCached();
        $this->artisan('interpresso:export-translations-deployment')
            ->expectsOutput('Language: en exported.')->expectsOutput('Language: de exported.')->assertExitCode(0);
        $this->assertSame(['welcome' => 'Welcome home'], require app()->langPath('en/e2e.php'));
        $this->assertSame(['en' => 'Model English', 'de' => 'Model German'], json_decode(DB::table('e2e_articles')->value('title'), true));
        $this->assertFalse(Translation::where('key', 'checkout')->firstOrFail()->exported);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('job_batches', 0);
    }

    #[Test]
    #[DataProvider('guardedCommands')]
    public function guarded_commands_leave_data_untouched_when_busy(string $command, array $options, bool $batchOnly): void
    {
        $this->seedBrowserScenario('running');
        if ($batchOnly) DB::table('jobs')->delete();
        else DB::table('job_batches')->delete();
        $before = Translation::orderBy('id')->get()->toArray();
        $this->artisan($command, $options)->expectsOutput('Another process is running: ' . resolve(\AnyMedia\Interpresso\Services\ProcessLock::class)->description() . '.')->assertExitCode(0);
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertSame(2, Language::count());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertFileDoesNotExist(app()->langPath('en/e2e.php'));
        $this->assertTrue(Setting::getCached()->process_running);
    }

    public static function guardedCommands(): iterable
    {
        foreach ([false, true] as $batchOnly) {
            foreach (['import-languages', 'import-translations', 'find-missing-translations', 'export-translations', 'export-translations-deployment', 'developer-download', 'prune-batches', 'send-automatic-pending-translations-notification'] as $command) {
                yield ['interpresso:' . $command, [], $batchOnly];
            }
            yield ['interpresso:export-translations', ['--force' => '0'], $batchOnly];
            yield ['interpresso:export-translations', ['--force' => '1'], $batchOnly];
            yield ['interpresso:approve-translations', ['--translator' => '1'], $batchOnly];
        }
    }

    #[Test]
    #[DataProvider('failingCommands')]
    public function command_errors_always_release_the_running_flag(string $command, string $service, string $method): void
    {
        $this->seedBrowserScenario();
        Setting::firstOrFail()->update(['db_loader' => false]);
        Setting::getFreshCached();
        $this->mock($service)->shouldReceive($method)->once()->andThrow(new \TypeError('Injected command failure'));
        try {
            $this->artisan($command)->run();
            $this->fail('The command must propagate its failure.');
        } catch (\TypeError $error) {
            $this->assertSame('Injected command failure', $error->getMessage());
        }
        $this->assertFalse(Setting::firstOrFail()->process_running);
        $this->assertFalse(Setting::getCached()->process_running);
    }

    public static function failingCommands(): array
    {
        return [
            ['interpresso:import-languages', ImportLanguageService::class, 'importLanguages'],
            ['interpresso:import-translations', ImportTranslationService::class, 'importTranslations'],
            ['interpresso:find-missing-translations', MissingTranslationService::class, 'findMissingTranslations'],
            ['interpresso:export-translations', ExportTranslationService::class, 'exportTranslationForLanguage'],
        ];
    }

    #[Test]
    public function pruning_deletes_only_package_batches_older_than_the_configured_retention(): void
    {
        $this->seedBrowserScenario('running');
        config(['interpresso.prune_batch_hours' => 2]);
        $template = (array) DB::table('job_batches')->first();
        foreach ([['old-finished', -121, null], ['old-cancelled', null, -121], ['recent', -119, null], ['boundary', -120, null], ['foreign', -121, null]] as [$id, $finished, $cancelled]) {
            DB::table('job_batches')->insert(array_replace($template, ['id' => $id, 'name' => $id === 'foreign' ? 'unrelated' : config('interpresso.batch_name'),
                'finished_at' => $finished === null ? null : now()->addMinutes($finished)->timestamp,
                'cancelled_at' => $cancelled === null ? null : now()->addMinutes($cancelled)->timestamp]));
        }
        // Pruning now shares the gate with all other commands. Finish the fixture first.
        DB::table('job_batches')->where('id', $template['id'])->update(['finished_at' => now()->timestamp]);
        DB::table('jobs')->delete();
        resolve(\AnyMedia\Interpresso\Services\ProcessLock::class)->forceRelease();
        $this->freezeTime();
        $this->artisan('interpresso:prune-batches')->assertExitCode(0);
        $this->assertEqualsCanonicalizing([$template['id'], 'recent', 'boundary', 'foreign'], DB::table('job_batches')->pluck('id')->all());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertFalse(Setting::getCached()->process_running);
    }

    #[Test]
    #[DataProvider('notificationSettings')]
    public function automatic_reminders_deliver_real_database_notifications_and_mail_only_to_assigned_recipients(bool $enabled): void
    {
        $this->seedBrowserScenario();
        $this->useDatabaseQueue();
        Setting::firstOrFail()->update(['enable_automatic_pending_notifications' => $enabled, 'enable_pending_notifications' => false]);
        Setting::getFreshCached();
        $admin = Translator::where('admin', true)->firstOrFail();
        $admin->languages()->sync(Language::pluck('id')->all());
        $withoutPendingWork = $this->createUser(Language::where('code', 'de')->get());
        $unassignedAdmin = $this->createAdmin(Language::whereRaw('1 = 0')->get());
        $mail = [];
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$mail): void {
            $mail[] = $event->message;
        });
        $this->artisan('interpresso:send-automatic-pending-translations-notification')->assertExitCode(0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertCount(0, $mail);
        $this->runWorker();
        $this->assertDatabaseCount('notifications', $enabled ? 2 : 0);
        $this->assertCount($enabled ? 2 : 0, $mail);
        if ($enabled) {
            $expected = __('interpresso::pending-translations-notification.message', ['total' => 2]);
            $recipients = Translator::whereHas('languages', fn ($query) => $query->where('code', 'en'))->get();
            foreach ($recipients as $recipient) {
                $notification = $recipient->notifications()->sole();
                $this->assertSame(PendingTranslationsNotification::class, $notification->type);
                $this->assertSame($expected, $notification->data['message']);
            }
            $this->assertEqualsCanonicalizing($recipients->pluck('email')->all(), array_map(fn ($message) => $message->getTo()[0]->getAddress(), $mail));
            foreach ($mail as $message) {
                $this->assertStringContainsString($expected, $message->getTextBody());
            }
        }
        $this->assertSame(0, $withoutPendingWork->notifications()->count());
        $this->assertSame(0, $unassignedAdmin->notifications()->count());
        $this->assertDatabaseCount('jobs', 0);
        // Reminders are queued notifications, not a batch.
        $this->assertDatabaseCount('job_batches', 0);
    }

    public static function notificationSettings(): array
    {
        return [[true], [false]];
    }
}
