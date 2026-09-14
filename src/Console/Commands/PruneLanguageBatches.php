<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PruneLanguageBatches extends Command
{
    use ChecksForRunningJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:prune-batches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all batches from the batch table which belong to the language package.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) return;
        try {
            /** @var string|\UnitEnum|null $connection Configured database connection name. */
            $connection = config('interpresso.db_connection');
            DB::connection($connection)->table('job_batches')->where('name', config('interpresso.batch_name'))
                ->where(function (Builder $query) {
                    /** @var int|float $hours Retention interval from config/interpresso.php. */
                    $hours = config('interpresso.prune_batch_hours');
                    $query->where('finished_at', '<', now()->subHours($hours)->timestamp)
                        ->orWhere('cancelled_at', '<', now()->subHours($hours)->timestamp);
                })->delete();
        } finally {
            $lock->release();
        }
    }
}
