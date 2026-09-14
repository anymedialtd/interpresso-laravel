<?php

namespace AnyMedia\Interpresso\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use AnyMedia\Interpresso\Services\ProcessLock;
use Illuminate\Support\Carbon;

class SettingsController extends Controller
{
    public function jobsOnOtherDBRunning(ProcessLock $lock): JsonResponse
    {
        $process_running = false;
        if(DB::table('jobs')
                ->where('queue', config('interpresso.queue_name'))
                ->exists() || DB::table('job_batches')->where('name', config('interpresso.batch_name'))
                ->whereNull('cancelled_at')
                ->whereNull('finished_at')
                ->exists()) {
            $process_running = true;
        }

        $current = $lock->current();
        // Derive status from the same snapshot as the metadata. A second read
        // could otherwise pair a new owner's live flag with an old owner's expiry.
        $leaseRunning = isset($current['expires_at']) && Carbon::parse($current['expires_at'])->gte(now()->startOfSecond());
        return response()->json([
            'process_running' => $process_running || $leaseRunning,
            'queue_running' => $process_running,
            'process_owner' => $current['owner'] ?? null,
            'process_started_at' => $current['started_at'] ?? null,
            'process_expires_at' => $current['expires_at'] ?? null,
        ]);
    }
}
