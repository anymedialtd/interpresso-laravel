<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Jobs\ApproveLanguagesJob;
use AnyMedia\Interpresso\Jobs\ExportTranslationJob;
use AnyMedia\Interpresso\Jobs\FindMissingTranslationsJob;
use AnyMedia\Interpresso\Jobs\ForceExportTranslationJob;
use AnyMedia\Interpresso\Jobs\ImportLanguagesJob;
use AnyMedia\Interpresso\Jobs\ImportTranslationsJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\QueueConfiguration;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;

class HttpQueueConfigurationTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    private function prepareAction(string $route): string
    {
        $this->seedBrowserScenario('models');
        File::ensureDirectoryExists(app()->langPath('it'));
        File::put(app()->langPath('en/import.php'), "<?php return ['new' => 'New source'];");
        config(['interpresso.api_shared_api_key' => 'queue-test-key']);
        $params = str_starts_with($route, 'interpresso.translations.')
            ? ['language' => Language::where('code', 'en')->firstOrFail()] : [];
        return route($route, $params);
    }

    private function databaseSnapshot(): array
    {
        return array_map(fn ($table) => DB::table($table)->get()->toJson(), [
            config('interpresso.table_languages'), config('interpresso.table_translations'),
            config('interpresso.table_settings'), config('interpresso.table_translators'),
            config('interpresso.table_translator_language'), 'e2e_articles',
            'jobs', 'job_batches', 'notifications', 'failed_jobs',
        ]);
    }

    #[Test]
    #[DataProvider('refusedActions')]
    public function non_deferring_http_actions_refuse_before_any_database_or_file_write(string $driver, string $route, array $data, string $command): void
    {
        $url = $this->prepareAction($route);
        // Use an alias: the connection name alone does not tell us its driver.
        config(['queue.default' => 'maintenance', 'queue.connections.maintenance' => ['driver' => $driver]]);
        Queue::fake();
        $command = str_replace(':translator', (string) Translator::where('admin', true)->firstOrFail()->id, $command);
        $before = $this->databaseSnapshot();
        if ($route === 'interpresso.api.force-export') {
            $this->postJson($url, ['api_key' => 'queue-test-key'])->assertStatus(503)
                ->assertJsonPath('message', QueueConfiguration::refusalMessage($command));
        } else {
            $this->post($url, $data)->assertRedirect()->assertSessionMissing('batch_id')
                ->assertSessionHas('toast.type', 'WARNING')
                ->assertSessionHas('toast.message', QueueConfiguration::refusalMessage($command));
        }
        $this->assertSame($before, $this->databaseSnapshot());
        $this->assertFileDoesNotExist(app()->langPath('en/e2e.php'));
        $this->assertFileDoesNotExist(app()->langPath('vendor/e2e-vendor/en/e2e.php'));
        Queue::assertNothingPushed();
    }

    public static function refusedActions(): iterable
    {
        foreach (['sync', 'null', 'deferred'] as $driver) {
            foreach (self::actions() as $name => [$route, $data, $command]) {
                yield "$driver / $name" => [$driver, $route, $data, $command];
            }
        }
    }

    #[Test]
    #[DataProvider('actions')]
    public function deferring_http_actions_enqueue_a_batch_without_executing_it(string $route, array $data, string $command, string $job): void
    {
        $url = $this->prepareAction($route);
        config(['queue.default' => 'maintenance', 'queue.connections.maintenance' => ['driver' => 'database']]);
        Queue::fake();
        $translations = Translation::orderBy('id')->get()->toArray();
        $languages = Language::orderBy('id')->get()->toArray();
        $models = DB::table('e2e_articles')->get()->toJson();
        if ($route === 'interpresso.api.force-export') {
            $this->postJson($url, ['api_key' => 'queue-test-key'])->assertOk();
        } else {
            $this->post($url, $data)->assertRedirect()->assertSessionHas('batch_id');
        }
        Queue::assertPushed($job);
        $this->assertDatabaseCount('job_batches', 1);
        $batch = Bus::findBatch(DB::table('job_batches')->value('id'));
        $this->assertGreaterThan(0, $batch->totalJobs);
        $this->assertSame($batch->totalJobs, $batch->pendingJobs);
        $this->assertFalse($batch->finished());
        $this->assertSame($translations, Translation::orderBy('id')->get()->toArray());
        $this->assertSame($languages, Language::orderBy('id')->get()->toArray());
        $this->assertSame($models, DB::table('e2e_articles')->get()->toJson());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertFileDoesNotExist(app()->langPath('en/e2e.php'));
        $this->assertFileDoesNotExist(app()->langPath('vendor/e2e-vendor/en/e2e.php'));
    }

    public static function actions(): array
    {
        return [
            'import languages' => ['interpresso.languages.import-languages', [], 'php artisan interpresso:import-languages', ImportLanguagesJob::class],
            'import translations' => ['interpresso.languages.import-translations', [], 'php artisan interpresso:import-translations', ImportTranslationsJob::class],
            'find missing' => ['interpresso.languages.find-missing', [], 'php artisan interpresso:find-missing-translations', FindMissingTranslationsJob::class],
            'approve all' => ['interpresso.languages.approve', [], 'php artisan interpresso:approve-translations --translator=:translator', ApproveLanguagesJob::class],
            'approve language' => ['interpresso.translations.approve-all', [], "php artisan interpresso:approve-translations --translator=:translator --language='en'", ApproveLanguagesJob::class],
            'export all' => ['interpresso.languages.export', [], 'php artisan interpresso:export-translations', ExportTranslationJob::class],
            'export language' => ['interpresso.translations.export', [], "php artisan interpresso:export-translations --language='en'", ExportTranslationJob::class],
            'export all models' => ['interpresso.languages.export', ['exportOnlyModels' => true], 'php artisan interpresso:export-translations --only-models', ExportTranslationJob::class],
            'export language models' => ['interpresso.translations.export', ['exportOnlyModels' => true], "php artisan interpresso:export-translations --language='en' --only-models", ExportTranslationJob::class],
            'force export API' => ['interpresso.api.force-export', [], 'php artisan interpresso:export-translations-deployment', ForceExportTranslationJob::class],
        ];
    }

    #[Test]
    public function sync_auto_translation_refuses_before_saving_the_root_draft(): void
    {
        $this->seedBrowserScenario('examples');
        Setting::firstOrFail()->update(['enable_open_ai_translations' => true]);
        Setting::getFreshCached();
        Queue::fake();
        $root = Translation::where('language_code', 'en')->where('key', 'welcome')->firstOrFail();
        $before = Translation::orderBy('id')->get()->toArray();
        $this->post(route('interpresso.translations.update-all', ['language' => $root->language, 'id' => $root->id]), ['translatedValue' => 'Must not save'])
            ->assertRedirect()->assertSessionMissing('batch_id')->assertSessionHas('toast.type', 'WARNING')
            ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'php artisan queue:work'));
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('job_batches', 0);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[DataProvider('connectionConfigurations')]
    public function queue_detection_checks_all_failover_paths_and_rejects_missing_configuration(array $connections, bool $expected): void
    {
        config(['queue.default' => 'maintenance', 'queue.connections' => $connections]);
        $this->assertSame($expected, QueueConfiguration::defersWork());
    }

    public static function connectionConfigurations(): array
    {
        return [
            'missing connection' => [[], false],
            'missing driver' => [['maintenance' => []], false],
            'null driver' => [['maintenance' => ['driver' => null]], false],
            'empty failover' => [['maintenance' => ['driver' => 'failover', 'connections' => []]], false],
            'cyclic failover' => [['maintenance' => ['driver' => 'failover', 'connections' => ['maintenance']]], false],
            'sync fallback' => [['maintenance' => ['driver' => 'failover', 'connections' => ['db', 'inline']], 'db' => ['driver' => 'database'], 'inline' => ['driver' => 'sync']], false],
            'deferred fallback' => [['maintenance' => ['driver' => 'failover', 'connections' => ['db', 'inline']], 'db' => ['driver' => 'database'], 'inline' => ['driver' => 'deferred']], false],
            'safe failover' => [['maintenance' => ['driver' => 'failover', 'connections' => ['db', 'redis']], 'db' => ['driver' => 'database'], 'redis' => ['driver' => 'redis']], true],
        ];
    }
}
