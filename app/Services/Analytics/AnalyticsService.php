<?php

namespace App\Services\Analytics;

use App\Models\Click;
use App\Models\Url;
use App\Services\Cache\UrlCacheService;

/**
 * Aggregates click data for analytics endpoints.
 * Results are cached by UrlCacheService.
 */
final class AnalyticsService
{
    public function __construct(
        private readonly UrlCacheService $cache,
    ) {}

    /**
     * Build and cache a comprehensive stats payload for a URL.
     */
    public function getStats(Url $url, bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cached = $this->cache->getStats($url->short_code);
            if ($cached !== null) {
                return $cached;
            }
        }

        $stats = $this->buildStats($url);
        $this->cache->putStats($url->short_code, $stats);

        return $stats;
    }

    // ─── Private builders ─────────────────────────────────────────────────────

    private function buildStats(Url $url): array
    {
        $baseQuery = Click::where('url_id', $url->id);

        return [
            'url_id'        => $url->id,
            'short_code'    => $url->short_code,
            'short_url'     => $url->short_url,
            'total_clicks'  => (clone $baseQuery)->count(),
            'unique_clicks' => (clone $baseQuery)->where('is_unique', true)->count(),

            // Time series: last 30 days grouped by date
            'clicks_over_time' => $this->clicksOverTime($url->id),

            // Top N breakdowns
            'top_countries'     => $this->topGroupBy($url->id, 'country_name', 10),
            'top_cities'        => $this->topGroupBy($url->id, 'city', 10),
            'device_breakdown'  => $this->topGroupBy($url->id, 'device_type', 5),
            'browser_breakdown' => $this->topGroupBy($url->id, 'browser', 10),
            'os_breakdown'      => $this->topGroupBy($url->id, 'os', 10),
            'top_referrers'     => $this->topGroupBy($url->id, 'referrer_host', 10),

            'computed_at' => now()->toIso8601String(),
        ];
    }

    private function clicksOverTime(int $urlId): array
    {
        return Click::where('url_id', $urlId)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as clicks')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'clicks' => (int) $row->clicks])
            ->toArray();
    }

    private function topGroupBy(int $urlId, string $column, int $limit): array
    {
        return Click::where('url_id', $urlId)
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, COUNT(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->toArray();
    }
}
