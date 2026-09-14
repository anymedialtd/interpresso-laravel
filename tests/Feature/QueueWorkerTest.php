<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ImportLanguagesJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class QueueWorkerTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    #[Test]
    public function an_empty_queue_exits_successfully(): void
    {
        $this->useDatabaseQueue();

        $this->artisan('interpresso:work')->assertExitCode(0);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('job_batches', 0);
    }

    #[Test]
    public function a_real_batch_is_drained_from_the_configured_queue_and_other_queues_are_left_alone(): void
    {
        $this->seedBrowserScenario();
        $this->useDatabaseQueue();
        config(['interpresso.queue_name' => 'custom-translations']);
        File::ensureDirectoryExists(app()->langPath('it'));
        Queue::pushOn('unrelated', new ImportLanguagesJob());
        $batch = resolve(BatchProcessor::class)->dispatch([new ImportLanguagesJob()]);
        $this->assertFalse(Language::where('code', 'it')->exists());
        $this->assertSame(1, $batch->pendingJobs);

        $this->artisan('interpresso:work')->assertExitCode(0);

        $batch = $batch->fresh();
        $this->assertTrue(Language::where('code', 'it')->exists());
        $this->assertTrue($batch->finished());
        $this->assertSame(0, $batch->pendingJobs);
        $this->assertSame(0, $batch->failedJobs);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'unrelated']);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[Test]
    public function an_http_batch_and_its_notifications_complete_without_a_long_running_worker(): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $this->post(route('interpresso.languages.approve'))->assertRedirect()->assertSessionHas('batch_id');
        $id = session('batch_id');
        $this->assertGreaterThan(0, Translation::where('approved', false)->count());
        $this->assertGreaterThan(0, Bus::findBatch($id)->pendingJobs);
        $this->assertTrue(Setting::getCached()->process_running);

        $this->artisan('interpresso:work')->assertExitCode(0);

        $this->assertSame(0, Translation::where('approved', false)->count());
        $this->assertSuccessfulBatch(array_map(fn ($language) => __('interpresso::translations.approved_language_success',
            ['language' => $language, 'total' => $language === 'English' ? 3 : 1]) . __('interpresso::global.reload_suggestion'), ['English', 'German']));
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[Test]
    #[DataProvider('budgets')]
    public function a_budget_stops_the_worker_with_work_left_for_the_next_invocation(string $limit): void
    {
        $this->seedBrowserScenario('bulk');
        $this->useDatabaseQueue();
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $id = session('batch_id');
        config(['interpresso.queue_worker.' . $limit => 1]);
        if ($limit === 'max_time') {
            // Laravel uses a monotonic clock for worker runtime. Make one real
            // job cross the budget instead of changing Carbon's wall clock.
            $delayNextJob = true;
            Queue::after(function () use (&$delayNextJob): void {
                if ($delayNextJob) {
                    $delayNextJob = false;
                    usleep(1_100_000);
                }
            });
        }

        $this->artisan('interpresso:work')->assertExitCode(0);

        $this->assertSame(1, Bus::findBatch($id)->pendingJobs);
        $this->assertFalse(Bus::findBatch($id)->finished());
        $this->assertTrue(Setting::getCached()->process_running);
        config(['interpresso.queue_worker.max_jobs' => 100, 'interpresso.queue_worker.max_time' => 50]);

        $this->artisan('interpresso:work')->assertExitCode(0);

        $this->assertTrue(Bus::findBatch($id)->finished());
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertDatabaseCount('jobs', 0);
    }

    public static function budgets(): array
    {
        return [['max_jobs'], ['max_time']];
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function invalid_limits_cannot_disable_the_worker_bounds(string $name, mixed $value): void
    {
        $this->useDatabaseQueue();
        config(['interpresso.queue_worker.' . $name => $value]);

        $this->artisan('interpresso:work')
            ->expectsOutput('interpresso.queue_worker.' . $name . ' must be a positive integer.')
            ->assertExitCode(1);
    }

    public static function invalidLimits(): iterable
    {
        foreach (['max_time', 'max_jobs', 'memory', 'timeout'] as $name) {
            foreach ([0, -1, 'invalid', null] as $value) {
                yield $name . ':' . var_export($value, true) => [$name, $value];
            }
        }
    }

    #[Test]
    public function sync_is_rejected_without_starting_a_worker(): void
    {
        $this->artisan('interpresso:work')
            ->expectsOutput('Interpresso requires a background queue. Set QUEUE_CONNECTION=database before running interpresso:work.')
            ->assertExitCode(1);
    }
}
