<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessClickAnalytics;
use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_short_code_redirects_to_original_url(): void
    {
        Queue::fake();

        $url = Url::factory()->create([
            'original_url' => 'https://example.com/destination',
            'short_code'   => 'abc1234',
        ]);

        $this->get('/abc1234')
            ->assertRedirect('https://example.com/destination')
            ->assertStatus(301);
    }

    public function test_redirect_dispatches_analytics_job(): void
    {
        Queue::fake();

        $url = Url::factory()->create(['short_code' => 'track1']);

        $this->get('/track1');

        Queue::assertPushedOn(
            config('url-shortener.analytics_queue'),
            ProcessClickAnalytics::class,
            fn ($job) => $job->url->id === $url->id,
        );
    }

    public function test_unknown_short_code_returns_404(): void
    {
        $this->get('/doesnotexist')
            ->assertNotFound();
    }

    public function test_expired_url_returns_410(): void
    {
        Queue::fake();

        Url::factory()->expired()->create(['short_code' => 'gone123']);

        $this->get('/gone123')
            ->assertStatus(410)
            ->assertJson(['message' => 'This short URL has expired.']);
    }

    public function test_inactive_url_returns_410(): void
    {
        Queue::fake();

        Url::factory()->inactive()->create(['short_code' => 'off1234']);

        $this->get('/off1234')
            ->assertStatus(410);
    }

    public function test_redirect_uses_cache_on_second_request(): void
    {
        Queue::fake();

        $url   = Url::factory()->create(['short_code' => 'cached1']);
        $cache = app(UrlCacheService::class);

        // First request — DB miss, warms cache
        $this->get('/cached1')->assertRedirect();

        // Verify the URL is now in cache
        $cachedUrl = $cache->get('cached1');
        $this->assertNotNull($cachedUrl);
        $this->assertSame($url->id, $cachedUrl->id);

        // Second request — should be a cache hit (no additional DB query)
        $this->get('/cached1')->assertRedirect();
    }

    public function test_redirect_increments_redis_click_counter(): void
    {
        Queue::fake();

        $url   = Url::factory()->create(['short_code' => 'count01']);
        $cache = app(UrlCacheService::class);

        $this->get('/count01');
        $this->get('/count01');

        // Drain the counter and assert it was incremented twice
        $count = $cache->drainClickCount('count01');
        $this->assertSame(2, $count);
    }

    public function test_analytics_job_not_dispatched_for_404(): void
    {
        Queue::fake();

        $this->get('/nosuchcode');

        Queue::assertNothingPushed();
    }
}
