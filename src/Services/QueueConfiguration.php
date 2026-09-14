<?php

namespace AnyMedia\Interpresso\Services;

class QueueConfiguration
{
    /** @return array{max_time: int, max_jobs: int, memory: int, timeout: int} */
    public static function workerLimits(): array
    {
        return [
            'max_time' => self::positiveWorkerLimit('max_time', 50),
            'max_jobs' => self::positiveWorkerLimit('max_jobs', 100),
            'memory' => self::positiveWorkerLimit('memory', 128),
            'timeout' => self::positiveWorkerLimit('timeout', 60),
        ];
    }

    private static function positiveWorkerLimit(string $name, int $default): int
    {
        $value = filter_var(config('interpresso.queue_worker.' . $name, $default), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        // Laravel treats zero as unlimited for several options. Reject it, and
        // malformed values, instead of accidentally starting a perpetual worker.
        if ($value === false) {
            throw new \InvalidArgumentException('interpresso.queue_worker.' . $name . ' must be a positive integer.');
        }

        return $value;
    }

    public static function workerOverlapMinutes(): int
    {
        $limits = self::workerLimits();

        // max-time is checked between jobs; allow the last job its timeout and
        // another minute for startup/shutdown before an abandoned mutex expires.
        return (int) ceil($limits['max_time'] / 60 + $limits['timeout'] / 60) + 1;
    }

    public static function defersWork(): bool
    {
        return self::connectionDefersWork(config('queue.default'));
    }

    /** @param list<string> $visited */
    private static function connectionDefersWork(mixed $connection, array $visited = []): bool
    {
        if (!is_string($connection) || $connection === '' || in_array($connection, $visited, true)) {
            return false;
        }

        $driver = config('queue.connections.' . $connection . '.driver');
        // Laravel's deferred driver also runs in the terminating HTTP process.
        if (!is_string($driver) || in_array($driver, ['', 'sync', 'null', 'deferred'], true)) {
            return false;
        }

        if ($driver === 'failover') {
            $connections = config('queue.connections.' . $connection . '.connections');
            if (!is_array($connections) || $connections === []) {
                return false;
            }
            foreach ($connections as $fallback) {
                if (!self::connectionDefersWork($fallback, [...$visited, $connection])) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function refusalMessage(string $command): string
    {
        return __('interpresso::global.queue_required', ['command' => $command]);
    }
}
