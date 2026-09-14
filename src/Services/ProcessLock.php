<?php

namespace AnyMedia\Interpresso\Services;

use AnyMedia\Interpresso\Models\Setting;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** A database lease for the package's single settings row. Never read it from cache. */
class ProcessLock
{
    private ?string $owner = null;
    private ?string $startedAt = null;
    private int $ttlSeconds = 1800;

    public static function owner(string $operation): string
    {
        // The nonce distinguishes repeated invocations in one PHP/queue worker process.
        return substr((gethostname() ?: 'unknown-host') . ':' . getmypid() . ' ' . $operation, 0, 210)
            . ' [' . Str::uuid()->toString() . ']';
    }

    public static function defaultTtl(): int
    {
        $ttl = config('interpresso.process_lock_ttl', 1800);
        if (!is_numeric($ttl) || (int) $ttl < 1) {
            throw new \InvalidArgumentException('interpresso.process_lock_ttl must be positive.');
        }
        return (int) $ttl;
    }

    public function acquire(string $owner, int $ttlSeconds): bool
    {
        if ($owner === '' || strlen($owner) > 255 || $ttlSeconds < 1) {
            throw new \InvalidArgumentException('A process lock requires an owner of 1-255 bytes and a positive TTL.');
        }
        $now = now();
        // One conditional UPDATE is the arbiter, including when two callers arrive together.
        $affected = $this->query()->where(function (Builder $query) use ($now): void {
            $query->where('process_running', false)
                ->orWhereNull('process_expires_at')
                ->orWhere('process_expires_at', '<', $now);
        })->update([
            'process_running' => true,
            'process_owner' => $owner,
            'process_started_at' => $now,
            'process_expires_at' => $now->copy()->addSeconds($ttlSeconds),
        ]);
        if ($affected !== 1) {
            return false;
        }

        $this->owner = $owner;
        $this->startedAt = $now->toDateTimeString();
        $this->ttlSeconds = $ttlSeconds;
        $this->forgetSettings();
        return true;
    }

    public function refresh(): void
    {
        if ($this->owner === null) {
            return;
        }
        $now = now();
        $expiresAt = $now->copy()->addSeconds($this->ttlSeconds);
        $owned = $this->ownedQuery()->where('process_expires_at', '>=', $now);
        // Some MySQL configurations count changed rows, so a heartbeat within the
        // same second can return zero even though the lease is still ours.
        if ($owned->update(['process_expires_at' => $expiresAt]) === 0 && !$owned->exists()) {
            throw new \RuntimeException('The process lock expired or was replaced.');
        }
        $this->forgetSettings();
    }

    public function release(): void
    {
        if ($this->owner === null) {
            return;
        }
        $this->clear($this->ownedQuery());
        $this->owner = null;
        $this->startedAt = null;
    }

    public function isLocked(): bool
    {
        return $this->query()->where('process_running', true)
            ->where('process_expires_at', '>=', now())->exists();
    }

    /** @return array{owner: string|null, started_at: string|null, expires_at: string|null}|null */
    public function current(): ?array
    {
        $setting = Setting::query()->useWritePdo()->where('process_running', true)->first();
        return $setting === null ? null : [
            'owner' => $setting->process_owner,
            'started_at' => $setting->process_started_at?->toIso8601String(),
            'expires_at' => $setting->process_expires_at?->toIso8601String(),
        ];
    }

    public function forceRelease(): void
    {
        $this->clear($this->query());
        $this->owner = null;
        $this->startedAt = null;
    }

    /** Clear only stale leases; a concurrent acquire/heartbeat must win safely. */
    public function releaseExpired(): bool
    {
        return $this->clear($this->query()->where(function (Builder $query): void {
            $query->where('process_running', false)->orWhereNull('process_expires_at')
                ->orWhere('process_expires_at', '<', now());
        })) > 0 || !$this->isLocked();
    }

    /** Transfer this handle to serialized batch callbacks without releasing the DB lease. */
    public function transfer(): self
    {
        if ($this->owner === null) {
            throw new \LogicException('Cannot transfer an unowned process lock.');
        }
        $next = clone $this;
        $this->owner = null;
        $this->startedAt = null;
        return $next;
    }

    /** @param array{owner: string|null, started_at: string|null, expires_at: string|null}|null $current */
    public function description(?array $current = null): string
    {
        $current ??= $this->current();
        return __('interpresso::global.process_description', [
            'owner' => $current['owner'] ?? __('interpresso::global.unknown_owner'),
            'started' => $current['started_at'] ?? __('interpresso::global.unknown'),
        ]);
    }

    private function query(): Builder
    {
        return Setting::query()->toBase()->useWritePdo();
    }

    private function ownedQuery(): Builder
    {
        return $this->query()->where('process_running', true)->where('process_owner', $this->owner)
            ->where('process_started_at', $this->startedAt);
    }

    private function clear(Builder $query): int
    {
        $affected = $query->update([
            'process_running' => false, 'process_owner' => null,
            'process_started_at' => null, 'process_expires_at' => null,
        ]);
        $this->forgetSettings();
        return $affected;
    }

    private function forgetSettings(): void
    {
        /** @var string $prefix */
        $prefix = config('interpresso.cache_key');
        Cache::forget($prefix . '_settings');
    }
}
