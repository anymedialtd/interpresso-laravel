<?php

namespace AnyMedia\Interpresso\Jobs\Job;

use AnyMedia\Interpresso\Services\QueueConfiguration;
use AnyMedia\Interpresso\Models\Translation;

abstract class ChunkedJob extends BaseJob
{
    protected int $chunkSize = 100;

    // Lost reservations (SIGKILL, host process caps) may replay. Actual exceptions
    // fail immediately, avoiding endless retries of invalid files or lost leases.
    public int $tries = 0;
    public int $maxExceptions = 1;

    public function __construct()
    {
        parent::__construct();
        // Keep the size in the payload so a config change cannot move a retry's boundary.
        $this->chunkSize = QueueConfiguration::chunkSize();
    }

    protected function startChunk(): bool
    {
        $batch = $this->batch();
        if (($this->batchId !== null && $batch === null) || $batch?->cancelled()) {
            return false;
        }
        $this->processLock()?->refresh();
        return true;
    }

    protected function finishChunk(?BaseJob $next = null): void
    {
        $batch = $this->batch();
        if ($batch?->cancelled()) {
            return;
        }
        // A crash can occur after the DB commit but before cache invalidation.
        // Even a replay that finds every row already written must advance the version.
        Translation::invalidateCacheAfterWrite();
        // Heartbeat at both ends, including the last chunk before a cron worker exits.
        $this->processLock()?->refresh();
        if ($next !== null) {
            // A cloned cursor keeps configuration, never the current queue reservation.
            $next->job = null;
            $batch?->add($next);
        }
    }

    protected function estimateChunks(int $rows): int
    {
        // A full slice schedules one more job, including an empty terminal slice.
        return intdiv($rows, $this->chunkSize) + 1;
    }
}
