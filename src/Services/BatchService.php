<?php

namespace AnyMedia\Interpresso\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

class BatchService {

    /**
     * @return array{int, int} Deleted jobs and cancelled batches.
     */
    public function deleteBatches(): array
    {
        // Capture the batch's handle before cancellation. Never clear a cron lease
        // or a replacement batch that starts while these records are removed.
        $locks = [];
        foreach (DB::table('job_batches')->where('name', config('interpresso.batch_name'))
            ->whereNull('cancelled_at')->whereNull('finished_at')->pluck('id') as $id) {
            if (is_string($id)) {
                $lock = Bus::findBatch($id)?->options['process_lock'] ?? null;
                if ($lock instanceof ProcessLock) {
                    $locks[] = $lock;
                }
            }
        }
        $batches = DB::table('job_batches')
            ->where('name', config('interpresso.batch_name'))
            ->whereNull('cancelled_at')
            ->whereNull('finished_at')
            ->update(['cancelled_at' => now()->timestamp]);
        $jobs = DB::table('jobs')
            ->where('queue', config('interpresso.queue_name'))
            ->delete();
        foreach ($locks as $lock) {
            $lock->release();
        }
        resolve(ProcessLock::class)->releaseExpired();
        return [$jobs, $batches];

    }
    /** @return array{id: string|null, progress: int, finished: bool, cancelled: bool, failed: bool} */
    public function progress(?string $id = null): array
    {
        if ($id === null) {
            $value = DB::table('job_batches')->where('name', config('interpresso.batch_name'))
                ->whereNull('finished_at')->whereNull('cancelled_at')->orderByDesc('created_at')->value('id');
            $id = is_string($value) ? $value : null;
        }
        $batch = $id !== null ? Bus::findBatch($id) : null;
        if ($batch !== null && $batch->name !== config('interpresso.batch_name')) {
            abort(403);
        }
        return [
            'id' => $batch?->id,
            'progress' => $batch?->progress() ?? 0,
            'finished' => $batch === null || $batch->finished() || $batch->cancelled(),
            'cancelled' => $batch?->cancelled() ?? false,
            'failed' => ($batch->failedJobs ?? 0) > 0,
        ];
    }
}
