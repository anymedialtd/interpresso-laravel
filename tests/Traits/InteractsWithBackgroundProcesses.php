<?php

namespace AnyMedia\Interpresso\Tests\Traits;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;

trait InteractsWithBackgroundProcesses
{
    private array $expectedMessages = [];

    private function seedBrowserScenario(string $scenario = 'base'): void
    {
        File::deleteDirectory(app()->langPath());
        foreach (['en', 'de', 'vendor'] as $directory) {
            File::ensureDirectoryExists(app()->langPath($directory));
        }
        require dirname(__DIR__) . '/e2e/fixtures.php';
        Setting::getFreshCached();
        config(['mail.default' => 'array']);
        $this->actingAs(Translator::where('email', 'admin@admin.com')->firstOrFail());
    }

    private function assertSuccessfulBatch(array $messages): void
    {
        $id = session('batch_id');
        $this->assertNotEmpty($id);
        $batch = Bus::findBatch($id);
        $this->assertNotNull($batch);
        $this->assertGreaterThan(0, $batch->totalJobs);
        $this->assertSame(0, $batch->pendingJobs);
        $this->assertSame(0, $batch->failedJobs);
        $this->assertFalse($batch->cancelled());
        $this->assertTrue($batch->finished());
        $this->assertSame(100, $batch->progress());
        $this->assertFalse(Setting::firstOrFail()->process_running);
        $this->assertFalse(Setting::getCached()->process_running);
        $this->assertDatabaseCount('jobs', 0);
        $this->getJson(route('interpresso.batch.progress', ['id' => $id]))
            ->assertOk()->assertJsonPath('finished', true)->assertJsonPath('progress', 100);
        $this->assertAdminMessages($messages);
    }

    private function assertAdminMessages(array $messages): void
    {
        array_push($this->expectedMessages, ...$messages);
        $admin = Translator::where('email', 'admin@admin.com')->firstOrFail();
        $this->assertEqualsCanonicalizing($this->expectedMessages,
            $admin->notifications()->where('type', FlashMessage::class)->get()->pluck('data.message')->all());
        $this->assertSame(0, Translator::where('admin', false)->firstOrFail()->notifications()->count());
        // Delivery must reach the UI endpoint as well as the notifications table.
        $this->getJson(route('interpresso.notifications'))->assertOk()
            ->assertJsonCount(count($this->expectedMessages), 'notifications');
    }

    private function useDatabaseQueue(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'testbench',
            'queue.connections.database.after_commit' => false,
        ]);
    }

    private function runWorker(bool $once = false): void
    {
        $this->artisan('queue:work', [
            'connection' => 'database', '--queue' => config('interpresso.queue_name'),
            '--stop-when-empty' => true, '--once' => $once, '--tries' => 1, '--sleep' => 0,
        ])->assertExitCode(0);
    }
}
