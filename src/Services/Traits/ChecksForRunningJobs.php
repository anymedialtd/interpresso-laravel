<?php

namespace AnyMedia\Interpresso\Services\Traits;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use AnyMedia\Interpresso\Services\Toast;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Services\ProcessLock;

trait ChecksForRunningJobs
{

    /** Checks if another job is running
     * @return bool
     */
    protected function anotherJobIsRunning(bool $fromCommandLine = false, bool $checkOtherHosts = true): bool
    {
        $lock = resolve(ProcessLock::class);
        if ($lock->isLocked()) {
            $this->jobIsRunningMessage($fromCommandLine, details: $lock->description());
            return true;
        }

        if (DB::table('jobs')
            ->where('queue', config('interpresso.queue_name'))
            ->exists() || DB::table('job_batches')->where('name', config('interpresso.batch_name'))
            ->whereNull('cancelled_at')
            ->whereNull('finished_at')
            ->exists()) {
            $this->jobIsRunningMessage($fromCommandLine);
            return true;
        }

        if (!$checkOtherHosts || !Setting::multiHostEnabled()) {
            $this->jobIsRunningMessage($fromCommandLine, false);
            return false;
        }

        $hosts = array_filter(array_map('trim', explode(',', Setting::getDomains() ?? '')));

        if($hosts) {
            $hosts = array_diff($hosts, [request()->getSchemeAndHttpHost()]);
            $path = route('interpresso.api.jobs-running', [], false);
            $responses = Http::pool(function (Pool $pool) use ($hosts, $path) {
                $poolArray = [];
                foreach($hosts as $host) {
                   $poolArray[] = $pool->post($host . $path, ['api_key' => config('interpresso.api_shared_api_key')]);
                }
                return $poolArray;
            });
            foreach($responses as $response) {
                // Ignore unreachable/misconfigured hosts so local jobs are not blocked.
                if(!$response instanceof Response || !$response->ok()) {
                    continue;
                }

                try{
                    if((bool) $response->json('process_running', false)) {
                        $expiresAt = $response->json('process_expires_at');
                        if (is_string($expiresAt) && \Illuminate\Support\Carbon::parse($expiresAt)->lt(now()->startOfSecond())
                            && !$response->json('queue_running', false)) {
                            continue;
                        }
                        $owner = $response->json('process_owner');
                        $startedAt = $response->json('process_started_at');
                        $details = is_string($owner) ? $lock->description([
                            'owner' => $owner,
                            'started_at' => is_string($startedAt) ? $startedAt : null,
                            'expires_at' => null,
                        ]) : null;
                        $this->jobIsRunningMessage($fromCommandLine, details: $details);
                        return true;
                    }
                } catch(\Exception $e) {
                    $this->jobIsRunningMessage($fromCommandLine);
                    return true;
                }
            }
        }

        $this->jobIsRunningMessage($fromCommandLine, false);
        return false;
    }

    /** Acquire after the advisory checks; only the conditional UPDATE grants entry. */
    protected function acquireProcessLock(string $operation, bool $fromCommandLine = false, bool $checkOtherHosts = true): ?ProcessLock
    {
        if ($this->anotherJobIsRunning($fromCommandLine, $checkOtherHosts)) {
            return null;
        }
        $lock = resolve(ProcessLock::class);
        if (!$lock->acquire(ProcessLock::owner($operation), ProcessLock::defaultTtl())) {
            $this->jobIsRunningMessage($fromCommandLine, details: $lock->description());
            return null;
        }
        return $lock;
    }

    protected function jobIsRunningMessage(bool $fromCommandLine, bool $isRunning = true, ?string $details = null): void
    {
        if($fromCommandLine) {
            if($isRunning) {
                $this->sendCommandInfo($details === null ? 'Another Process is running.' : 'Another process is running: ' . $details . '.');
            }
        } else {
            if ($isRunning) {
                Toast::flash(
                    __('interpresso::global.import.processing_no_action') . ($details === null ? '' : ' ' . $details . '.'),
                    'WARNING'
                );
            } else {
                Toast::flash(
                    __('interpresso::global.import.start_message'),
                    'SUCCESS',
                    6000
                );
            }
        }

    }

    private function sendCommandInfo(string $message): void
    {
        if(method_exists($this, 'info')) {
            /** @var callable(string):void $info */
            $info = [$this, 'info'];
            $info($message);
        }
    }

}
