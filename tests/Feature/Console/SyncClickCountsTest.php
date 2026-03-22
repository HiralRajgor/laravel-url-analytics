<?php

namespace Tests\Feature\Console;

use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncClickCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_flushes_redis_counters_to_database(): void
    {
        $url   = Url::factory()->create(['short_code' => 'sync01', 'click_count' => 10]);
        $cache = app(UrlCacheService::class);

        // Simulate 5 new clicks recorded in Redis
        $cache->incrementClickCount('sync01');
        $cache->incrementClickCount('sync01');
        $cache->incrementClickCount('sync01');
        $cache->incrementClickCount('sync01');
        $cache->incrementClickCount('sync01');

        $this->artisan('urls:sync-click-counts')
            ->assertSuccessful();

        // DB click_count should have been incremented from 10 → 15
        $this->assertDatabaseHas('urls', [
            'id'          => $url->id,
            'click_count' => 15,
        ]);
    }

    public function test_dry_run_does_not_write_to_database(): void
    {
        $url   = Url::factory()->create(['short_code' => 'dry01', 'click_count' => 5]);
        $cache = app(UrlCacheService::class);

        $cache->incrementClickCount('dry01');
        $cache->incrementClickCount('dry01');

        $this->artisan('urls:sync-click-counts --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Would update');

        // click_count must remain unchanged
        $this->assertDatabaseHas('urls', [
            'id'          => $url->id,
            'click_count' => 5,
        ]);
    }

    public function test_command_skips_urls_with_no_pending_increments(): void
    {
        Url::factory()->count(3)->create();

        $this->artisan('urls:sync-click-counts')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 0 URL(s)');
    }
}
