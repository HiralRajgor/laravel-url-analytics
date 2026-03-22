<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessClickAnalytics;
use App\Models\Click;
use App\Models\Url;
use App\Services\Analytics\DeviceDetectionService;
use App\Services\Analytics\GeoIpService;
use App\Services\Cache\UrlCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessClickAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private GeoIpService $geoIp;

    private DeviceDetectionService $device;

    private UrlCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Full mocks — job tests shouldn't make HTTP calls or hit Redis
        $this->geoIp  = Mockery::mock(GeoIpService::class);
        $this->device = Mockery::mock(DeviceDetectionService::class);
        $this->cache  = Mockery::mock(UrlCacheService::class);
    }

    public function test_job_persists_click_with_enriched_data(): void
    {
        $url = Url::factory()->create();

        $this->geoIp->shouldReceive('resolve')
            ->once()
            ->with('1.2.3.4')
            ->andReturn([
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'city'         => 'London',
            ]);

        $this->device->shouldReceive('detect')
            ->once()
            ->andReturn(['device_type' => 'desktop', 'browser' => 'Chrome', 'os' => 'macOS']);

        $this->cache->shouldReceive('recordUniqueClick')
            ->once()
            ->andReturn(true);

        $this->cache->shouldReceive('forgetStats')
            ->once()
            ->with($url->short_code);

        $job = new ProcessClickAnalytics(
            url: $url,
            ipAddress: '1.2.3.4',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/122',
            referrer: 'https://twitter.com/sometweet',
        );

        $job->handle($this->geoIp, $this->device, $this->cache);

        $this->assertDatabaseHas('clicks', [
            'url_id'        => $url->id,
            'ip_address'    => '1.2.3.4',
            'country_code'  => 'GB',
            'country_name'  => 'United Kingdom',
            'city'          => 'London',
            'device_type'   => 'desktop',
            'browser'       => 'Chrome',
            'os'            => 'macOS',
            'referrer_host' => 'twitter.com',
            'is_unique'     => true,
        ]);
    }

    public function test_job_marks_duplicate_click_as_non_unique(): void
    {
        $url = Url::factory()->create();

        $this->geoIp->shouldReceive('resolve')->andReturn(['country_code' => null, 'country_name' => null, 'city' => null]);
        $this->device->shouldReceive('detect')->andReturn(['device_type' => 'desktop', 'browser' => null, 'os' => null]);

        // Second click from same IP — recordUniqueClick returns false
        $this->cache->shouldReceive('recordUniqueClick')->andReturn(false);
        $this->cache->shouldReceive('forgetStats');

        $job = new ProcessClickAnalytics($url, '1.2.3.4', 'UA', null);
        $job->handle($this->geoIp, $this->device, $this->cache);

        $this->assertDatabaseHas('clicks', [
            'url_id'    => $url->id,
            'is_unique' => false,
        ]);
    }

    public function test_referrer_host_is_extracted_correctly(): void
    {
        $url = Url::factory()->create();

        $this->geoIp->shouldReceive('resolve')->andReturn(['country_code' => null, 'country_name' => null, 'city' => null]);
        $this->device->shouldReceive('detect')->andReturn(['device_type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS']);
        $this->cache->shouldReceive('recordUniqueClick')->andReturn(true);
        $this->cache->shouldReceive('forgetStats');

        $job = new ProcessClickAnalytics($url, '5.5.5.5', 'UA', 'https://www.google.com/search?q=test');
        $job->handle($this->geoIp, $this->device, $this->cache);

        $this->assertDatabaseHas('clicks', [
            'referrer_host' => 'google.com', // www. stripped
        ]);
    }
}
