<?php

namespace AnyMedia\Interpresso\Services;

class QueueConfiguration
{
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
