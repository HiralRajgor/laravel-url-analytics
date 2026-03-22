<?php

namespace Tests\Unit\Services;

use App\Services\Analytics\DeviceDetectionService;
use Tests\TestCase;

class DeviceDetectionServiceTest extends TestCase
{
    private DeviceDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviceDetectionService;
    }

    /** @dataProvider userAgentProvider */
    public function test_detects_device_type_correctly(
        string $userAgent,
        string $expectedDeviceType,
    ): void {
        $result = $this->service->detect($userAgent);

        $this->assertSame($expectedDeviceType, $result['device_type']);
    }

    public static function userAgentProvider(): array
    {
        return [
            'Chrome desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'desktop',
            ],
            'iPhone Safari' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'mobile',
            ],
            'iPad' => [
                'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'tablet',
            ],
            'Googlebot' => [
                'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'bot',
            ],
        ];
    }

    public function test_returns_browser_name(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

        $result = $this->service->detect($ua);

        $this->assertNotNull($result['browser']);
        $this->assertIsString($result['browser']);
    }

    public function test_returns_os_name(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

        $result = $this->service->detect($ua);

        $this->assertNotNull($result['os']);
        $this->assertIsString($result['os']);
    }

    public function test_handles_empty_user_agent_gracefully(): void
    {
        $result = $this->service->detect('');

        $this->assertArrayHasKey('device_type', $result);
        $this->assertArrayHasKey('browser', $result);
        $this->assertArrayHasKey('os', $result);
    }
}
