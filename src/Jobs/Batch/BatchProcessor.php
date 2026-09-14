<?php

namespace AnyMedia\Interpresso\Jobs\Batch;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use Throwable;

class BatchProcessor
{
    /**
     * @var Translator
     */
    protected Translator $authUser;

    /**
     *
     * @param list<BaseJob> $batchArray
     * @param (Closure(): void)|null $then
     * @param (Closure(): void)|null $catch
     * @param (Closure(): void)|null $finally
     * @return PendingBatch
     */
    public function execute(array $batchArray, Closure|null $then = null, Closure|null $catch = null, Closure|null $finally = null): PendingBatch
    {
        /** @var string $batchName Configured package batch name. */
        $batchName = config('interpresso.batch_name');
        /** @var string|\UnitEnum|null $queue Configured queue name. */
        $queue = config('interpresso.queue_name');
        return Bus::batch($batchArray)
            ->before(function (Batch $batch): void {
                Setting::setJobsRunning();
            })
            ->then(function (Batch $batch) use ($then) {
                if ($batch->cancelled()) return;
                if ($then) $then();
                Log::info('Jobs in batch id ' . $batch->id . ' successfully completed.');
            })->catch(function (Batch $batch, Throwable $e) use ($catch) {
                try {
                    if ($catch) $catch();
                    Log::error('Batch with id ' . $batch->id . ' failed.');
                } finally {
                    Setting::setJobsRunning(false);
                }
            })->finally(function (Batch $batch) use ($finally) {
                try {
                    if ($finally) $finally();
                    Log::info('Batch id ' . $batch->id . ' has finished executing.');
                } finally {
                    Setting::setJobsRunning(false);
                }
            })->name($batchName)->onQueue($queue);
    }

    /**
     * Dispatch immediately, also releasing the flag if enqueueing or sync execution fails.
     * Laravel rolls back batch bookkeeping and notifications on a sync failure.
     *
     * @param list<BaseJob> $jobs
     * @param (Closure(): void)|null $then
     */
    public function dispatch(array $jobs, ?Closure $then = null): Batch
    {
        if ($jobs === []) {
            throw new \InvalidArgumentException('A language batch requires at least one job.');
        }
        try {
            return $this->execute($jobs, $then)->dispatch();
        } catch (Throwable $e) {
            Setting::setJobsRunning(false);
            $jobs[0]->failed($e);
            throw $e;
        }
    }
}
