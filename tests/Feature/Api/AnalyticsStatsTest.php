<?php

namespace Tests\Feature\Api;

use App\Models\Click;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_returns_expected_shape(): void
    {
        $url = Url::factory()->create(['short_code' => 'stats01']);

        Click::factory()->count(10)->create([
            'url_id'    => $url->id,
            'is_unique' => true,
        ]);

        Click::factory()->count(5)->create([
            'url_id'    => $url->id,
            'is_unique' => false,
        ]);

        $this->getJson('/api/v1/urls/stats01/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'url_id', 'short_code', 'short_url',
                    'total_clicks', 'unique_clicks',
                    'clicks_over_time',
                    'top_countries',
                    'top_cities',
                    'device_breakdown',
                    'browser_breakdown',
                    'os_breakdown',
                    'top_referrers',
                    'computed_at',
                ],
            ])
            ->assertJsonPath('data.total_clicks', 15)
            ->assertJsonPath('data.unique_clicks', 10);
    }

    public function test_stats_returns_404_for_unknown_code(): void
    {
        $this->getJson('/api/v1/urls/unknown/stats')
            ->assertNotFound();
    }

    public function test_stats_are_cached_on_second_call(): void
    {
        $url = Url::factory()->create(['short_code' => 'cache01']);
        Click::factory()->count(3)->create(['url_id' => $url->id]);

        // First call builds stats
        $first = $this->getJson('/api/v1/urls/cache01/stats')
            ->assertOk()
            ->json('data.total_clicks');

        // Add a click after the cache is warm
        Click::factory()->create(['url_id' => $url->id]);

        // Second call should return cached value (still 3, not 4)
        $second = $this->getJson('/api/v1/urls/cache01/stats')
            ->assertOk()
            ->json('data.total_clicks');

        $this->assertSame($first, $second);
    }
}
