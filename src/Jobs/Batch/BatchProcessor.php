<?php

namespace AnyMedia\Interpresso\Jobs\Batch;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\ProcessLock;
use Throwable;

class BatchProcessor
{
    /**
     *
     * @param list<BaseJob> $batchArray
     * @param (Closure(): void)|null $then
     * @param (Closure(): void)|null $catch
     * @param (Closure(): void)|null $finally
     * @return PendingBatch
     */
    private function execute(array $batchArray, ProcessLock $lock, Closure|null $then = null, Closure|null $catch = null, Closure|null $finally = null): PendingBatch
    {
        /** @var string $batchName Configured package batch name. */
        $batchName = config('interpresso.batch_name');
        /** @var string|\UnitEnum|null $queue Configured queue name. */
        $queue = config('interpresso.queue_name');
        return Bus::batch($batchArray)
            ->withOption('process_lock', $lock)
            ->then(function (Batch $batch) use ($then) {
                if ($batch->cancelled()) return;
                if ($then) $then();
                Log::info('Jobs in batch id ' . $batch->id . ' successfully completed.');
            })->catch(function (Batch $batch, Throwable $e) use ($catch, $lock) {
                try {
                    if ($catch) $catch();
                    Log::error('Batch with id ' . $batch->id . ' failed.');
                } finally {
                    $lock->release();
                }
            })->finally(function (Batch $batch) use ($finally, $lock) {
                try {
                    if ($finally) $finally();
                    Log::info('Batch id ' . $batch->id . ' has finished executing.');
                } finally {
                    $lock->release();
                }
            })->name($batchName)->onQueue($queue);
    }

    /**
     * Dispatch immediately, releasing the lease if enqueueing or sync execution fails.
     * Laravel rolls back batch bookkeeping and notifications on a sync failure.
     *
     * @param list<BaseJob> $jobs
     * @param (Closure(): void)|null $then
     */
    public function dispatch(array $jobs, ?Closure $then = null, ?ProcessLock $lock = null): Batch
    {
        if ($jobs === []) {
            throw new \InvalidArgumentException('A language batch requires at least one job.');
        }
        $lock ??= $this->acquireLock();
        $batchLock = $lock->transfer();
        $dispatched = false;
        try {
            $batch = $this->execute($jobs, $batchLock, $then)->dispatch();
            $dispatched = true;
            return $batch;
        } catch (Throwable $e) {
            $jobs[0]->failed($e);
            throw $e;
        } finally {
            if (!$dispatched) {
                $batchLock->release();
            }
        }
    }

    /**
     * Keep ownership between the HTTP response and dispatch, including dispatch failures.
     * @param list<BaseJob> $jobs
     * @param (Closure(): void)|null $then
     */
    public function dispatchAfterResponse(array $jobs, ?Closure $then = null, ?ProcessLock $lock = null): void
    {
        $lock ??= $this->acquireLock();
        $deferredLock = $lock->transfer();
        $registered = false;
        try {
            app()->terminating(static function () use ($jobs, $then, $deferredLock): void {
                try {
                    if ($jobs !== []) {
                        resolve(self::class)->dispatch($jobs, $then, $deferredLock);
                    }
                } finally {
                    $deferredLock->release();
                }
            });
            $registered = true;
        } finally {
            if (!$registered) {
                $deferredLock->release();
            }
        }
    }

    private function acquireLock(): ProcessLock
    {
        $lock = resolve(ProcessLock::class);
        if (!$lock->acquire(ProcessLock::owner('translation batch'), ProcessLock::defaultTtl())) {
            throw new \RuntimeException('Another process is running: ' . $lock->description() . '.');
        }
        return $lock;
    }
}
