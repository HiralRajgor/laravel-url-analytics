<?php

namespace Tests\Unit\Services;

use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private UrlCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = app(UrlCacheService::class);
    }

    public function test_put_and_get_url(): void
    {
        $url = Url::factory()->create(['short_code' => 'test001']);

        $this->cache->put($url);

        $retrieved = $this->cache->get('test001');

        $this->assertNotNull($retrieved);
        $this->assertSame($url->id, $retrieved->id);
        $this->assertSame('test001', $retrieved->short_code);
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $result = $this->cache->get('nonexistent');
        $this->assertNull($result);
    }

    public function test_forget_removes_url_from_cache(): void
    {
        $url = Url::factory()->create(['short_code' => 'forget1']);
        $this->cache->put($url);

        $this->cache->forget('forget1');

        $this->assertNull($this->cache->get('forget1'));
    }

    public function test_first_unique_click_returns_true(): void
    {
        $result = $this->cache->recordUniqueClick('utest01', '10.0.0.1');
        $this->assertTrue($result);
    }

    public function test_second_click_from_same_ip_returns_false(): void
    {
        $this->cache->recordUniqueClick('utest02', '10.0.0.2');
        $result = $this->cache->recordUniqueClick('utest02', '10.0.0.2');

        $this->assertFalse($result);
    }

    public function test_different_ips_are_each_unique(): void
    {
        $a = $this->cache->recordUniqueClick('utest03', '10.0.0.3');
        $b = $this->cache->recordUniqueClick('utest03', '10.0.0.4');

        $this->assertTrue($a);
        $this->assertTrue($b);
    }

    public function test_increment_click_count_returns_incremented_value(): void
    {
        $count1 = $this->cache->incrementClickCount('clicktest1');
        $count2 = $this->cache->incrementClickCount('clicktest1');

        $this->assertSame(1, $count1);
        $this->assertSame(2, $count2);
    }

    public function test_drain_click_count_returns_count_and_resets(): void
    {
        $this->cache->incrementClickCount('drain01');
        $this->cache->incrementClickCount('drain01');
        $this->cache->incrementClickCount('drain01');

        $drained = $this->cache->drainClickCount('drain01');
        $this->assertSame(3, $drained);

        // After drain, counter is reset
        $afterDrain = $this->cache->drainClickCount('drain01');
        $this->assertSame(0, $afterDrain);
    }
}
