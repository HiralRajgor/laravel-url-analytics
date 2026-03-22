<?php

namespace Tests\Unit\Services;

use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use App\Services\Shortener\UrlShortenerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlShortenerServiceTest extends TestCase
{
    use RefreshDatabase;

    private UrlShortenerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UrlShortenerService::class);
    }

    public function test_shorten_creates_url_with_generated_code(): void
    {
        $url = $this->service->shorten([
            'original_url' => 'https://example.com/path',
        ]);

        $this->assertInstanceOf(Url::class, $url);
        $this->assertNotEmpty($url->short_code);
        $this->assertSame(7, strlen($url->short_code));
        $this->assertTrue($url->is_active);
        $this->assertDatabaseHas('urls', ['short_code' => $url->short_code]);
    }

    public function test_shorten_with_custom_slug(): void
    {
        $url = $this->service->shorten([
            'original_url' => 'https://example.com',
            'custom_slug'  => 'my-slug',
        ]);

        $this->assertSame('my-slug', $url->short_code);
        $this->assertSame('my-slug', $url->custom_slug);
    }

    public function test_shorten_warms_cache_immediately(): void
    {
        $url   = $this->service->shorten(['original_url' => 'https://example.com']);
        $cache = app(UrlCacheService::class);

        $cached = $cache->get($url->short_code);

        $this->assertNotNull($cached);
        $this->assertSame($url->id, $cached->id);
    }

    public function test_reserved_slug_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reserved/i');

        $this->service->shorten([
            'original_url' => 'https://example.com',
            'custom_slug'  => 'api', // reserved word
        ]);
    }

    public function test_duplicate_slug_throws_invalid_argument_exception(): void
    {
        Url::factory()->withCustomSlug('taken-slug')->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->shorten([
            'original_url' => 'https://example.com',
            'custom_slug'  => 'taken-slug',
        ]);
    }

    public function test_update_clears_and_rewarms_cache(): void
    {
        $url   = $this->service->shorten(['original_url' => 'https://example.com']);
        $cache = app(UrlCacheService::class);

        $this->service->update($url, ['title' => 'Updated Title']);

        // Cache should be refreshed with the new title
        $cached = $cache->get($url->short_code);
        $this->assertNotNull($cached);
        $this->assertSame('Updated Title', $cached->title);
    }

    public function test_deactivate_removes_from_cache(): void
    {
        $url   = $this->service->shorten(['original_url' => 'https://example.com']);
        $cache = app(UrlCacheService::class);

        $this->service->deactivate($url);

        $this->assertNull($cache->get($url->short_code));
        $this->assertDatabaseHas('urls', ['id' => $url->id, 'is_active' => false]);
    }

    public function test_delete_removes_url_and_cache(): void
    {
        $url   = $this->service->shorten(['original_url' => 'https://example.com']);
        $id    = $url->id;
        $code  = $url->short_code;
        $cache = app(UrlCacheService::class);

        $this->service->delete($url);

        $this->assertNull($cache->get($code));
        $this->assertDatabaseMissing('urls', ['id' => $id]);
    }

    public function test_generated_codes_are_unique(): void
    {
        // Generate 20 URLs and verify no duplicates
        $codes = collect(range(1, 20))->map(fn () => $this->service->shorten(['original_url' => 'https://example.com'])->short_code,
        );

        $this->assertSame($codes->count(), $codes->unique()->count());
    }

    public function test_short_url_attribute_includes_domain(): void
    {
        config(['url-shortener.domain' => 'https://sho.rt']);

        $url = $this->service->shorten(['original_url' => 'https://example.com']);

        $this->assertStringStartsWith('https://sho.rt/', $url->short_url);
    }
}
