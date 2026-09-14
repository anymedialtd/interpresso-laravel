<?php

namespace AnyMedia\Interpresso\Jobs\Job;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use AnyMedia\Interpresso\Jobs\Traits\HandlesFailedJobs;

abstract class BaseJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesFailedJobs;

    public function __construct()
    {
        /** @var string|\UnitEnum|null $queue Configured queue name. */
        $queue = config('interpresso.queue_name');
        $this->onQueue($queue);
    }

    /** @return list<SkipIfBatchCancelled> */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled()];
    }

    /**
     * @return void
     * @throws \Exception
     */
    abstract public function handle(): void;
}
