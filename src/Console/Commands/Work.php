<?php

namespace AnyMedia\Interpresso\Console\Commands;

use AnyMedia\Interpresso\Services\QueueConfiguration;
use Illuminate\Console\Command;
use InvalidArgumentException;

class Work extends Command
{
    protected $signature = 'interpresso:work
        {--max-time= : Maximum runtime in seconds, checked between chunks}
        {--memory= : Worker memory limit in MB, checked between chunks}';

    protected $description = 'Drain the Interpresso queue within configured limits, then exit.';

    public function handle(): int
    {
        if (!QueueConfiguration::defersWork()) {
            $this->error('Interpresso requires a background queue. Set QUEUE_CONNECTION=database before running interpresso:work.');

            return self::FAILURE;
        }

        try {
            $limits = QueueConfiguration::workerLimits($this->option('max-time'), $this->option('memory'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        // Pending batches already own their ProcessLock. The worker must be able
        // to consume those jobs without acquiring a competing operation lease.
        return $this->call('queue:work', [
            '--queue' => config('interpresso.queue_name'),
            '--stop-when-empty' => true,
            '--max-time' => $limits['max_time'],
            '--max-jobs' => $limits['max_jobs'],
            '--memory' => $limits['memory'],
            '--timeout' => $limits['timeout'],
            '--sleep' => 0,
            '--tries' => 1,
        ]);
    }
}
