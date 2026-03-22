<?php

namespace App\Services\Analytics;

use Jenssegers\Agent\Agent;

/**
 * Wraps jenssegers/agent for device/browser/OS detection.
 * Stateless — each call creates a fresh Agent instance.
 */
final class DeviceDetectionService
{
    /**
     * Parse a User-Agent string into structured device data.
     *
     * @return array{
     *   device_type: string,
     *   browser:     string|null,
     *   os:          string|null,
     * }
     */
    public function detect(string $userAgent): array
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        return [
            'device_type' => $this->resolveDeviceType($agent),
            'browser'     => $agent->browser() ?: null,
            'os'          => $agent->platform() ?: null,
        ];
    }

    private function resolveDeviceType(Agent $agent): string
    {
        if ($agent->isRobot()) {
            return 'bot';
        }

        if ($agent->isMobile()) {
            return 'mobile';
        }

        if ($agent->isTablet()) {
            return 'tablet';
        }

        return 'desktop';
    }
}
