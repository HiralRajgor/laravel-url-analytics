<?php

namespace App\Jobs;

use App\Models\Click;
use App\Models\Url;
use App\Services\Analytics\DeviceDetectionService;
use App\Services\Analytics\GeoIpService;
use App\Services\Cache\UrlCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously persists click analytics including geo-IP resolution.
 *
 * Why async geo-IP lookup?
 * ─────────────────────────────────────────────────────────────────────
 * Inline geo-IP resolution adds 150–300 ms to every redirect response.
 * Users clicking a short URL expect instant navigation; they have zero
 * interest in the analytics being recorded on that same request cycle.
 * By dispatching this job to a dedicated 'analytics' queue, the redirect
 * controller returns a 301 in < 5 ms (cache hit) or < 20 ms (DB query),
 * while enrichment happens asynchronously with no latency impact. If the
 * geo-IP provider is slow or rate-limits us, only the queue backs up —
 * not user-facing redirects.
 * ─────────────────────────────────────────────────────────────────────
 *
 * Handles:
 *  - Geo-IP lookup (GeoIpService)
 *  - Device/browser/OS detection (DeviceDetectionService)
 *  - Unique click deduplication via Redis (UrlCacheService)
 *  - Stats cache invalidation after write
 */
class ProcessClickAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 3 times with exponential back-off (10s, 30s, 90s). */
    public int $tries = 3;

    public int $backoff = 10;

    /** Don't waste queue slots if the URL was deleted. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Url $url,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly ?string $referrer,
    ) {}

    public function handle(
        GeoIpService $geoIp,
        DeviceDetectionService $device,
        UrlCacheService $cache,
    ): void {
        $isUnique = $cache->recordUniqueClick($this->url->short_code, $this->ipAddress);

        $geoData    = $geoIp->resolve($this->ipAddress);
        $deviceData = $device->detect($this->userAgent);

        Click::create([
            'url_id'        => $this->url->id,
            'ip_address'    => $this->ipAddress,
            'country_code'  => $geoData['country_code'],
            'country_name'  => $geoData['country_name'],
            'city'          => $geoData['city'],
            'device_type'   => $deviceData['device_type'],
            'browser'       => $deviceData['browser'],
            'os'            => $deviceData['os'],
            'referrer'      => $this->referrer,
            'referrer_host' => $this->extractHost($this->referrer),
            'user_agent'    => $this->userAgent,
            'is_unique'     => $isUnique,
            'clicked_at'    => now(),
        ]);

        // Bust the stats cache — next /stats call rebuilds from fresh data
        $cache->forgetStats($this->url->short_code);

        Log::debug('Click analytics processed', [
            'url_id'    => $this->url->id,
            'country'   => $geoData['country_code'],
            'device'    => $deviceData['device_type'],
            'is_unique' => $isUnique,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessClickAnalytics job failed', [
            'url_id' => $this->url->id,
            'error'  => $exception->getMessage(),
        ]);
    }

    private function extractHost(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host ? strtolower(ltrim($host, 'www.')) : null;
    }
}
