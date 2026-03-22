<?php

namespace Database\Factories;

use App\Models\Click;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClickFactory extends Factory
{
    protected $model = Click::class;

    private static array $countries = [
        ['GB', 'United Kingdom'], ['US', 'United States'], ['DE', 'Germany'],
        ['IN', 'India'], ['CA', 'Canada'], ['AU', 'Australia'], ['FR', 'France'],
    ];

    private static array $devices = ['desktop', 'mobile', 'tablet', 'bot'];

    private static array $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];

    private static array $oses = ['Windows', 'macOS', 'Linux', 'iOS', 'Android'];

    public function definition(): array
    {
        $country = fake()->randomElement(self::$countries);

        return [
            'url_id'        => Url::factory(),
            'ip_address'    => fake()->ipv4(),
            'country_code'  => $country[0],
            'country_name'  => $country[1],
            'city'          => fake()->city(),
            'device_type'   => fake()->randomElement(self::$devices),
            'browser'       => fake()->randomElement(self::$browsers),
            'os'            => fake()->randomElement(self::$oses),
            'referrer'      => fake()->optional(0.4)->url(),
            'referrer_host' => fake()->optional(0.4)->domainName(),
            'user_agent'    => fake()->userAgent(),
            'is_unique'     => fake()->boolean(70),
            'clicked_at'    => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
