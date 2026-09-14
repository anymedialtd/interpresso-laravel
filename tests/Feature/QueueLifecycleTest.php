<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Services\ApproveLanguagesService;
use AnyMedia\Interpresso\Services\ExportTranslationService;
use AnyMedia\Interpresso\Services\ImportLanguageService;
use AnyMedia\Interpresso\Services\ImportTranslationService;
use AnyMedia\Interpresso\Services\MissingTranslationService;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;

class QueueLifecycleTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    #[Test]
    public function a_real_queue_advances_progress_and_delivers_notifications_only_after_completion(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $id = session('batch_id');
        $this->assertTrue(Setting::getCached()->process_running);
        $this->assertDatabaseCount('notifications', 0);
        $this->getJson(route('interpresso.batch.progress'))->assertOk()
            ->assertJsonPath('id', $id)->assertJsonPath('progress', 0)->assertJsonPath('finished', false);
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('data-batch-id="' . $id . '"', false);
        $this->runWorker(once: true);
        $this->getJson(route('interpresso.batch.progress', ['id' => $id]))
            ->assertOk()->assertJsonPath('progress', 50)->assertJsonPath('finished', false);
        $this->assertTrue(Setting::getCached()->process_running);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(1, Translation::where('approved', false)->count());
        $this->runWorker();
        $this->assertSame(0, Translation::where('approved', false)->count());
        session()->flash('batch_id', $id);
        $this->assertSuccessfulBatch(array_map(fn ($language) => __('interpresso::translations.approved_language_success',
            ['language' => $language, 'total' => $language === 'English' ? 3 : 1]) . __('interpresso::global.reload_suggestion'), ['English', 'German']));
    }

    #[Test]
    public function cancellation_skips_a_reserved_job_and_allows_replacement_work(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $before = Translation::orderBy('id')->get()->toArray();
        $this->post(route('interpresso.translations.approve-all', Language::where('code', 'en')->firstOrFail()))->assertRedirect();
        $id = session('batch_id');
        // A worker can have reserved a job before the cancellation request.
        $reserved = Queue::connection('database')->pop(config('interpresso.queue_name'));
        $this->assertNotNull($reserved);
        $this->post(route('interpresso.languages.cancel-jobs'))->assertRedirect()->assertSessionHas('toast.type', 'SUCCESS');
        $this->assertDatabaseCount('jobs', 0);
        $reserved->fire();
        $this->runWorker();
        // Even the final reserved job must not invoke the success callback.
        $this->assertTrue(Bus::findBatch($id)->finished());
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->getJson(route('interpresso.batch.progress', ['id' => $id]))
            ->assertOk()->assertJsonPath('cancelled', true)->assertJsonPath('finished', true);
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $this->runWorker();
        $this->assertSame(0, Translation::where('approved', false)->count());
        $this->assertSuccessfulBatch(array_map(fn ($language) => __('interpresso::translations.approved_language_success',
            ['language' => $language, 'total' => $language === 'English' ? 3 : 1]) . __('interpresso::global.reload_suggestion'), ['English', 'German']));
    }

    #[Test]
    #[DataProvider('gatedActions')]
    public function every_gated_action_refuses_work_and_explains_why(string $name, bool $row, array $data, bool $batchOnly): void
    {
        $this->seedBrowserScenario('bulk');
        Setting::firstOrFail()->update(['enable_open_ai_translations' => true]);
        Setting::getFreshCached();
        File::ensureDirectoryExists(app()->langPath('it'));
        $this->useDatabaseQueue();
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        if ($batchOnly) {
            DB::table('jobs')->delete();
        } else {
            DB::table('job_batches')->delete();
        }
        $translation = Translation::where('key', 'profile')->firstOrFail();
        $params = str_starts_with($name, 'interpresso.translations.') ? ['language' => $translation->language] : [];
        if ($row) $params['id'] = $translation->id;
        $before = Translation::orderBy('id')->get()->toArray();
        $jobs = DB::table('jobs')->get()->toArray();
        $batches = DB::table('job_batches')->get()->toArray();
        $this->post(route($name, $params), $data)->assertRedirect()
            ->assertSessionHas('toast.message', __('interpresso::global.import.processing_no_action') . ' '
                . resolve(\AnyMedia\Interpresso\Services\ProcessLock::class)->description() . '.')
            ->assertSessionHas('toast.type', 'WARNING');
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertEquals($jobs, DB::table('jobs')->get()->toArray());
        $this->assertEquals($batches, DB::table('job_batches')->get()->toArray());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(2, Language::count());
        $this->assertFileDoesNotExist(app()->langPath('en/e2e.php'));
        $this->assertFileDoesNotExist(app()->langPath('vendor/e2e-vendor/en/e2e.php'));
    }

    public static function gatedActions(): iterable
    {
        foreach ([true, false] as $batchOnly) {
            foreach (['import-languages', 'import-translations', 'find-missing', 'approve', 'export'] as $action) {
                yield "$action / " . ($batchOnly ? 'batch' : 'queue') => ['interpresso.languages.' . $action, false, [], $batchOnly];
            }
            foreach (['approve-all', 'export', 'update', 'update-all', 'approve', 'request', 'restore-request', 'restore'] as $action) {
                yield "translation $action / " . ($batchOnly ? 'batch' : 'queue') => ['interpresso.translations.' . $action,
                    !in_array($action, ['approve-all', 'export']), ['translatedValue' => 'Must not save'], $batchOnly];
            }
            foreach (['interpresso.languages.export', 'interpresso.translations.export'] as $route) {
                yield "$route models / " . ($batchOnly ? 'batch' : 'queue') => [$route, false, ['exportOnlyModels' => true], $batchOnly];
            }
        }
    }

    #[Test]
    #[DataProvider('failingActions')]
    public function failed_ui_jobs_release_the_running_flag_and_send_only_a_failure_notification(string $route, string $service, string $method): void
    {
        $this->seedBrowserScenario('bulk');
        $this->mock($service)->shouldReceive($method)->once()->andThrow(new \TypeError('Injected worker failure'));
        $params = str_starts_with($route, 'interpresso.translations.') ? ['language' => Language::where('code', 'en')->firstOrFail()] : [];
        $this->post(route($route, $params))->assertStatus(500);
        // Laravel removes a batch when synchronous dispatch throws.
        $this->assertDatabaseCount('job_batches', 0);
        $this->assertFalse(Setting::firstOrFail()->process_running);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertAdminMessages([__('interpresso::global.something_wrong')]);
        $this->getJson(route('interpresso.batch.progress'))
            ->assertOk()->assertJsonPath('finished', true);
    }

    public static function failingActions(): array
    {
        return [
            ['interpresso.languages.import-languages', ImportLanguageService::class, 'importLanguages'],
            ['interpresso.languages.import-translations', ImportTranslationService::class, 'importTranslations'],
            ['interpresso.languages.find-missing', MissingTranslationService::class, 'findMissingTranslations'],
            ['interpresso.languages.approve', ApproveLanguagesService::class, 'approveLanguages'],
            ['interpresso.translations.approve-all', ApproveLanguagesService::class, 'approveLanguages'],
            ['interpresso.languages.export', ExportTranslationService::class, 'exportTranslationForLanguage'],
            ['interpresso.translations.export', ExportTranslationService::class, 'exportTranslationForLanguage'],
        ];
    }

    #[Test]
    public function a_database_worker_records_failure_skips_remaining_jobs_and_delivers_the_error(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $before = Translation::orderBy('id')->get()->toArray();
        $this->mock(ApproveLanguagesService::class)->shouldReceive('approveLanguages')->once()->andThrow(new \RuntimeException('Worker failed'));
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $id = session('batch_id');
        $this->runWorker();
        $this->assertSame($before, Translation::orderBy('id')->get()->toArray());
        $this->assertSame(1, Bus::findBatch($id)->failedJobs);
        $this->assertTrue(Bus::findBatch($id)->cancelled());
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertAdminMessages([__('interpresso::global.something_wrong')]);
    }
}
