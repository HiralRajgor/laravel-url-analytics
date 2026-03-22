<?php

namespace App\Services\Cache;

use App\Models\Url;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Centralises all URL cache interactions.
 *
 * Key scheme:
 *   url:{short_code}          → serialised Url model (resolve redirects)
 *   url_stats:{short_code}    → stats array (GET /urls/{code}/stats)
 *   url_list:{page}:{perPage} → paginated index result
 *   unique_click:{code}:{ip}  → flag to detect duplicate clicks
 */
final class UrlCacheService
{
    private int $urlTtl;

    private int $statsTtl;

    private int $uniqueWindow;

    public function __construct()
    {
        $this->urlTtl       = config('url-shortener.cache.url_ttl', 3600);
        $this->statsTtl     = config('url-shortener.cache.stats_ttl', 300);
        $this->uniqueWindow = 3600; // 1-hour unique-click dedup window
    }

    // ─── URL resolution cache ─────────────────────────────────────────────────

    public function get(string $shortCode): ?Url
    {
        try {
            $cached = Cache::get($this->urlKey($shortCode));
            return $cached ? unserialize($cached) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function put(Url $url): void
    {
        try {
            Cache::put(
                $this->urlKey($url->short_code),
                serialize($url),
                $this->urlTtl
            );
        } catch (\Throwable) {
            // Cache unavailable — DB fallback will handle it
        }
    }

    public function forget(string $shortCode): void
    {
        Cache::forget($this->urlKey($shortCode));
        Cache::forget($this->statsKey($shortCode));
    }

    // ─── Stats cache ──────────────────────────────────────────────────────────

    public function getStats(string $shortCode): ?array
    {
        return Cache::get($this->statsKey($shortCode));
    }

    public function putStats(string $shortCode, array $stats): void
    {
        Cache::put($this->statsKey($shortCode), $stats, $this->statsTtl);
    }

    public function forgetStats(string $shortCode): void
    {
        Cache::forget($this->statsKey($shortCode));
    }

    // ─── Atomic click counter ─────────────────────────────────────────────────

    /**
     * Atomically increment click_count in Redis.
     * Background job will periodically flush to DB.
     * Returns the new count.
     */
    public function incrementClickCount(string $shortCode): int
    {
        try {
            $key = "click_counter:{$shortCode}";
            return (int) Redis::incr($key);
        } catch (\Throwable) {
            // Redis not available — silently skip, counter will sync when Redis is up
            return 0;
        }
    }

    /**
     * Drain the Redis click counter into the return value and reset.
     * Used by the SyncClickCounts console command.
     */
    public function drainClickCount(string $shortCode): int
    {
        try {
            $key = "click_counter:{$shortCode}";
            $count = Redis::eval(
                "local v = redis.call('GET', KEYS[1]) if v then redis.call('DEL', KEYS[1]) return tonumber(v) else return 0 end",
                1,
                $key
            );
            return (int) $count;
        } catch (\Throwable) {
            return 0;
        }
    }


    // ─── Unique click detection ────────────────────────────────────────────────

    /**
     * Returns true if this IP/code combo has NOT been seen in the last hour.
     * Uses atomic SETNX (SET if Not eXists) to avoid race conditions.
     */
    public function recordUniqueClick(string $shortCode, string $ip): bool
    {
        try {
            $key = $this->uniqueKey($shortCode, $ip);
            $result = Redis::set($key, 1, 'EX', $this->uniqueWindow, 'NX');
            return $result !== null;
        } catch (\Throwable) {
            // Redis not available — treat every click as unique
            return true;
        }
    }

    // ─── Key builders ─────────────────────────────────────────────────────────

    private function urlKey(string $code): string
    {
        return "url:{$code}";
    }

    private function statsKey(string $code): string
    {
        return "url_stats:{$code}";
    }

    private function uniqueKey(string $code, string $ip): string
    {
        return 'unique_click:'.$code.':'.md5($ip);
    }
}
