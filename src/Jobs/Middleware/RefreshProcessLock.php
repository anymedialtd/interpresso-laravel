<?php

namespace AnyMedia\Interpresso\Jobs\Middleware;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use Closure;

class RefreshProcessLock
{
    /** @param Closure(BaseJob): mixed $next */
    public function handle(BaseJob $job, Closure $next): mixed
    {
        // A delayed job must not execute after its lease expired and was replaced.
        $job->processLock()?->refresh();
        return $next($job);
    }
}
