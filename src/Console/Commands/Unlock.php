<?php

namespace AnyMedia\Interpresso\Console\Commands;

use AnyMedia\Interpresso\Services\ProcessLock;
use Illuminate\Console\Command;

class Unlock extends Command
{
    protected $signature = 'interpresso:unlock {--force : Release a live lock as well as an expired lock}';

    protected $description = 'Show and release the Interpresso process lock.';

    public function handle(ProcessLock $lock): int
    {
        if ($lock->current() !== null) {
            $this->info('Process lock: ' . $lock->description() . '.');
        }
        if ($this->option('force')) {
            $lock->forceRelease();
        } elseif (!$lock->releaseExpired()) {
            $this->error('The lock is still active. Use --force to release it.');
            return self::FAILURE;
        }
        $this->info('Process lock released.');
        return self::SUCCESS;
    }
}
