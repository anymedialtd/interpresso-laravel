<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessSignaledException;

class ChunkWorkerRestartTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/interpresso-cursor-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    #[Test]
    public function an_export_completes_across_separate_workers_with_a_one_second_budget(): void
    {
        $seed = $this->runProcess('seed');
        $this->assertSame(6, $seed['estimate']);
        $first = $this->runProcess('budget');
        $this->assertSame(7, $first['exported']);
        $this->assertCount(8, $first['file']);
        $this->assertSame(1, $first['pending']);
        $this->assertFalse($first['finished']);
        $this->assertTrue($first['locked']);

        $finished = $this->runProcess('finish');

        $this->assertCompleteExport($finished);
        $this->assertSame($seed['id'], $finished['id']);
        $cursors = $this->cursors();
        $this->assertSame([0, 7, 14, 21, 28, 35], array_column($cursors, 'after'));
        $this->assertNotSame($cursors[0]['pid'], $cursors[1]['pid']);
    }

    #[Test]
    public function a_killed_reservation_replays_only_its_slice_without_corrupting_the_file(): void
    {
        $this->runProcess('seed');
        $this->runProcess('budget');
        $process = $this->process('crash');
        try {
            $process->run();
            $this->fail('Expected the worker to be killed during its slice.');
        } catch (ProcessSignaledException) {
            // SIGKILL is the expected host interruption, not a PHP exception.
        }
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertSame(SIGKILL, $process->getTermSignal());
        $interrupted = $this->runProcess('inspect');
        $this->assertSame(7, $interrupted['exported']);
        $this->assertCount(15, $interrupted['file']);
        $this->assertTrue($interrupted['locked']);
        usleep(1_100_000); // Let the abandoned reservation's one-second retry_after elapse.

        $this->assertCompleteExport($this->runProcess('finish'));

        $cursors = $this->cursors();
        $this->assertSame([0, 7, 7, 14, 21, 28, 35], array_column($cursors, 'after'));
        $this->assertSame(2, $cursors[2]['attempt']);
    }

    private function assertCompleteExport(array $state): void
    {
        $expected = ['existing' => 'Keep this'];
        for ($i = 1; $i <= 37; $i++) $expected['key.' . $i] = 'Value ' . $i;
        $this->assertSame($expected, $state['file']);
        $this->assertSame(37, $state['exported']);
        $this->assertTrue($state['finished']);
        $this->assertFalse($state['locked']);
        foreach (['pending', 'jobs', 'failed', 'failed_jobs'] as $key) $this->assertSame(0, $state[$key], $key);
    }

    private function process(string $mode): Process
    {
        return new Process([PHP_BINARY, dirname(__DIR__) . '/fixtures/chunk-worker.php', $this->directory, $mode], timeout: 30);
    }

    private function runProcess(string $mode): array
    {
        $process = $this->process($mode);
        $process->mustRun();
        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function cursors(): array
    {
        return array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            file($this->directory . '/cursors', FILE_IGNORE_NEW_LINES));
    }
}
