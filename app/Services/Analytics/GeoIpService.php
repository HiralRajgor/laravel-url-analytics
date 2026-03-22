<?php

namespace App\Services\Analytics;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around ipapi.co geo-IP resolution.
 *
 * Designed for easy mocking in tests via constructor injection.
 * Always fails gracefully — geo data is analytics enrichment, never critical.
 */
final class GeoIpService
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Resolve an IP address to geographic data.
     *
     * @return array{
     *   country_code: string|null,
     *   country_name: string|null,
     *   city:         string|null,
     * }
     */
    public function resolve(string $ip): array
    {
        $null = ['country_code' => null, 'country_name' => null, 'city' => null];

        if ($this->isPrivateIp($ip)) {
            return $null;
        }

        try {
            $baseUrl = rtrim(config('url-shortener.geo_ip.base_url'), '/');
            $timeout = config('url-shortener.geo_ip.timeout', 3);

            $response = $this->http
                ->timeout($timeout)
                ->get("{$baseUrl}/{$ip}/json/");

            if (! $response->successful()) {
                return $null;
            }

            $data = $response->json();

            return [
                'country_code' => $data['country_code'] ?? null,
                'country_name' => $data['country_name'] ?? null,
                'city'         => $data['city']         ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GeoIP lookup failed', [
                'ip'    => $ip,
                'error' => $e->getMessage(),
            ]);

            return $null;
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
