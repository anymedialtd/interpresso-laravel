<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use AnyMedia\Interpresso\InterpressoServiceProvider;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ExportTranslationJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\Foundation\Application;

// Only these separate worker processes share a scratch SQLite file. The regular
// Testbench suite retains its in-memory connection and never uses this database.
[$script, $directory, $mode] = $argv;
$app = Application::create(options: ['extra' => [
    'providers' => [InterpressoServiceProvider::class], 'dont-discover' => ['*'],
]]);
$app->useLangPath($directory . '/lang');
config([
    'app.locale' => 'en',
    'database.default' => 'testbench',
    'database.connections.testbench' => ['driver' => 'sqlite', 'database' => $directory . '/queue.sqlite', 'prefix' => ''],
    'interpresso.db_connection' => 'testbench',
    'interpresso.chunk_size' => 7,
    'queue.default' => 'database',
    'queue.connections.database.connection' => 'testbench',
    'queue.connections.database.retry_after' => 1,
    'queue.batching.database' => 'testbench',
    'queue.failed.database' => 'testbench',
    'cache.default' => 'array',
    'mail.default' => 'array',
]);

if ($mode === 'seed') {
    touch($directory . '/queue.sqlite');
    Artisan::call('migrate', ['--force' => true]);
    File::ensureDirectoryExists(app()->langPath('en'));
    File::put(app()->langPath('en.json'), '{"existing":"Keep this"}');
    $language = Language::where('code', 'en')->firstOrFail();
    for ($i = 1; $i <= 37; $i++) {
        Translation::create([
            'language_id' => $language->id, 'language_code' => 'en', 'shared_identifier' => 'cursor-' . $i,
            'type' => 'json', 'namespace' => '', 'group' => '', 'is_vendor' => false,
            'key' => 'key.' . $i, 'value' => 'Value ' . $i, 'approved' => true,
            'updated_translation' => false, 'needs_translation' => false, 'exported' => false,
        ]);
    }
    $batch = resolve(BatchProcessor::class)->dispatch([new ExportTranslationJob($language)]);
    File::put($directory . '/batch-id', $batch->id);
} elseif ($mode !== 'inspect') {
    Queue::before(function ($event) use ($directory): void {
        $job = unserialize($event->job->payload()['data']['command']);
        File::append($directory . '/cursors', json_encode([
            'after' => $job->afterId, 'attempt' => $event->job->attempts(), 'pid' => getmypid(),
        ]) . "\n");
    });
    if ($mode === 'budget') {
        // max-time is checked between jobs, not a per-job kill timer. Make the
        // first real chunk cross its one-second budget deterministically.
        Queue::after(static function (): void { usleep(1_100_000); });
    }
    if ($mode === 'crash') {
        DB::connection()->beforeExecuting(static function (string $sql): void {
            if (str_starts_with($sql, 'update "interpresso_translations" set "exported"')) {
                // File replacement has happened, but the exported flag and successor
                // have not. SIGKILL bypasses all PHP cleanup and queue acknowledgments.
                posix_kill(getmypid(), SIGKILL);
            }
        });
    }
    $exit = Artisan::call('interpresso:work', ['--max-time' => $mode === 'budget' ? 1 : 30]);
    if ($exit !== 0) {
        fwrite(STDERR, Artisan::output());
        exit($exit);
    }
}

$batch = Bus::findBatch(File::get($directory . '/batch-id'));
echo json_encode([
    'id' => $batch->id, 'finished' => $batch->finished(), 'pending' => $batch->pendingJobs,
    'estimate' => $batch->options['estimated_total_jobs'], 'failed' => $batch->failedJobs,
    'exported' => Translation::where('exported', true)->count(),
    'locked' => Setting::firstOrFail()->process_running,
    'jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(),
    'file' => json_decode(File::get(app()->langPath('en.json')), true, flags: JSON_THROW_ON_ERROR),
], JSON_THROW_ON_ERROR), "\n";
