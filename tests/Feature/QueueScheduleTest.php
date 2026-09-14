<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Services\ProcessCapabilities;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class QueueScheduleTest extends BaseTestCase
{
    private function enableSchedule(): void
    {
        config([
            'queue.default' => 'maintenance',
            'queue.connections.maintenance.driver' => 'database',
            'interpresso.schedule' => [
                'queue_worker' => true, 'prune_batches' => true, 'pending_notifications' => true,
            ],
        ]);
    }

    #[Test]
    public function enabled_schedules_are_registered_without_needing_database_settings(): void
    {
        $this->enableSchedule();
        $events = $this->app->make(Schedule::class)->events();

        $this->assertCount(3, $events);
        $this->assertStringContainsString('interpresso:prune-batches', $events[0]->command);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertStringContainsString('interpresso:send-automatic-pending-translations-notification', $events[1]->command);
        $this->assertSame('0 0 * * *', $events[1]->expression);
        $worker = $events[2];
        $this->assertStringContainsString('interpresso:work', $worker->command);
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertTrue($worker->runInBackground);
        $this->assertTrue($worker->withoutOverlapping);
        $this->assertSame(3, $worker->expiresAt);

        // Verify the actual cache mutex, including its abandoned-lock expiry.
        $this->freezeSecond();
        $this->assertTrue($worker->mutex->create($worker));
        try {
            $this->assertTrue($worker->shouldSkipDueToOverlapping());
            $this->assertFalse($worker->mutex->create($worker));
            $this->travel(181)->seconds();
            $this->assertFalse($worker->shouldSkipDueToOverlapping());
        } finally {
            $worker->mutex->forget($worker);
            $this->travelBack();
        }
    }

    #[Test]
    #[DataProvider('nonDeferringDrivers')]
    public function no_schedule_is_registered_for_non_deferring_drivers(?string $driver): void
    {
        $this->enableSchedule();
        config(['queue.connections.maintenance.driver' => $driver]);

        $this->assertSame([], $this->app->make(Schedule::class)->events());
    }

    public static function nonDeferringDrivers(): array
    {
        return [['sync'], ['null'], ['deferred'], [''], [null]];
    }

    #[Test]
    public function schedules_are_disabled_by_default_even_with_a_database_queue(): void
    {
        config(['queue.default' => 'database']);

        $this->assertSame([], $this->app->make(Schedule::class)->events());
    }

    #[Test]
    #[DataProvider('scheduleToggles')]
    public function every_schedule_can_be_enabled_independently(string $key, string $command): void
    {
        $this->enableSchedule();
        config(['interpresso.schedule' => [$key => true]]);
        $events = $this->app->make(Schedule::class)->events();

        $this->assertCount(1, $events);
        $this->assertStringContainsString($command, $events[0]->command);
    }

    public static function scheduleToggles(): array
    {
        return [
            ['queue_worker', 'interpresso:work'],
            ['prune_batches', 'interpresso:prune-batches'],
            ['pending_notifications', 'interpresso:send-automatic-pending-translations-notification'],
        ];
    }

    #[Test]
    public function overlap_expiry_allows_the_configured_time_plus_the_last_jobs_timeout(): void
    {
        $this->enableSchedule();
        config(['interpresso.queue_worker.max_time' => 120, 'interpresso.queue_worker.timeout' => 900]);

        $worker = $this->app->make(Schedule::class)->events()[2];

        $this->assertSame(18, $worker->expiresAt);
    }

    #[Test]
    #[DataProvider('processCapabilities')]
    public function process_detection_checks_disabled_functions_even_when_a_stub_exists(string $disabled, bool $exists, bool $expected): void
    {
        $capabilities = new class($disabled, $exists) extends ProcessCapabilities {
            public function __construct(private string $disabled, private bool $exists) {}
            protected function disabledFunctions(): string { return $this->disabled; }
            protected function functionExists(string $function): bool { return $this->exists; }
        };
        $this->assertSame($expected, $capabilities->canSpawn());
        $this->enableSchedule();
        $this->app->instance(ProcessCapabilities::class, $capabilities);
        $events = $this->app->make(Schedule::class)->events();
        $this->assertSame($expected, $events[2]->runInBackground);
        if (!$expected) {
            foreach ($events as $event) $this->assertInstanceOf(CallbackEvent::class, $event);
        }
    }

    public static function processCapabilities(): array
    {
        return [
            'available' => ['', true, true],
            'missing' => ['', false, false],
            'disabled stub' => ['exec, proc_open, shell_exec', true, false],
            'case and whitespace' => ['exec, PROC_OPEN ,shell_exec', true, false],
            'different function' => ['proc_close', true, true],
        ];
    }

    #[Test]
    public function background_execution_can_be_disabled_even_when_proc_open_is_available(): void
    {
        $this->enableSchedule();
        config(['interpresso.schedule.worker_background' => false]);
        $this->mock(ProcessCapabilities::class)->shouldReceive('canSpawn')->once()->andReturn(true);
        $worker = $this->app->make(Schedule::class)->events()[2];
        $this->assertInstanceOf(CallbackEvent::class, $worker);
        $this->assertFalse($worker->runInBackground);
        $this->assertTrue($worker->withoutOverlapping);
        $this->assertSame(3, $worker->expiresAt);
    }
}
