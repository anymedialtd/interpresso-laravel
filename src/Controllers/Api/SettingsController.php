<?php

namespace AnyMedia\Interpresso\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function jobsOnOtherDBRunning(): JsonResponse
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

        return response()->json(['process_running' => $process_running]);
    }
}
