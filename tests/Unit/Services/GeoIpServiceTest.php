<?php

namespace Tests\Unit\Services;

use App\Services\Analytics\GeoIpService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoIpServiceTest extends TestCase
{
    private GeoIpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Inject the real Http facade — Http::fake() will intercept calls
        $this->service = new GeoIpService(Http::getFacadeRoot());
    }

    public function test_resolves_public_ip_to_geo_data(): void
    {
        Http::fake([
            'ipapi.co/8.8.8.8/json/' => Http::response([
                'country_code' => 'US',
                'country_name' => 'United States',
                'city'         => 'Mountain View',
            ], 200),
        ]);

        $result = $this->service->resolve('8.8.8.8');

        $this->assertSame('US', $result['country_code']);
        $this->assertSame('United States', $result['country_name']);
        $this->assertSame('Mountain View', $result['city']);
    }

    public function test_returns_nulls_for_private_ip(): void
    {
        Http::fake(); // Should never be called for private IPs

        $result = $this->service->resolve('192.168.1.1');

        $this->assertNull($result['country_code']);
        $this->assertNull($result['country_name']);
        $this->assertNull($result['city']);

        Http::assertNothingSent();
    }

    public function test_returns_nulls_when_provider_returns_error(): void
    {
        Http::fake([
            'ipapi.co/*' => Http::response([], 503),
        ]);

        $result = $this->service->resolve('8.8.8.8');

        $this->assertNull($result['country_code']);
        $this->assertNull($result['country_name']);
    }

    public function test_returns_nulls_when_provider_times_out(): void
    {
        Http::fake([
            'ipapi.co/*' => Http::response()->throw(
                new ConnectionException('cURL error 28: Operation timed out'),
            ),
        ]);

        // Must not throw — geo enrichment is non-critical
        $result = $this->service->resolve('8.8.8.8');

        $this->assertNull($result['country_code']);
    }

    public function test_calls_correct_endpoint(): void
    {
        Http::fake([
            'ipapi.co/1.2.3.4/json/' => Http::response([
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'city'         => 'Sydney',
            ]),
        ]);

        $this->service->resolve('1.2.3.4');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '1.2.3.4/json/'),
        );
    }
}
