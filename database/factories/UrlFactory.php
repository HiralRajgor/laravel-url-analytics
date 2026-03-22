<?php

namespace Database\Factories;

use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UrlFactory extends Factory
{
    protected $model = Url::class;

    public function definition(): array
    {
        return [
            'user_id'      => null,
            'original_url' => fake()->url(),
            'short_code'   => Str::random(7),
            'title'        => fake()->optional(0.6)->sentence(4),
            'custom_slug'  => null,
            'click_count'  => fake()->numberBetween(0, 10000),
            'is_active'    => true,
            'expires_at'   => fake()->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn () => ['user_id' => User::factory()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withCustomSlug(string $slug): static
    {
        return $this->state(fn () => [
            'short_code'  => $slug,
            'custom_slug' => $slug,
        ]);
    }
}
