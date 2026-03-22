<?php

namespace Database\Seeders;

use App\Models\Click;
use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo user with known credentials
        $user = User::factory()->create([
            'name'  => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        // Well-known short URLs for manual testing
        Url::factory()->withCustomSlug('github')->create([
            'user_id'      => $user->id,
            'original_url' => 'https://github.com',
            'title'        => 'GitHub',
        ]);

        Url::factory()->withCustomSlug('docs')->create([
            'user_id'      => $user->id,
            'original_url' => 'https://laravel.com/docs',
            'title'        => 'Laravel Docs',
        ]);

        // Bulk URLs with rich click data for analytics testing
        Url::factory()
            ->count(10)
            ->withUser()
            ->create()
            ->each(function (Url $url) {
                // 50–500 clicks per URL, spread over 30 days
                Click::factory()
                    ->count(fake()->numberBetween(50, 500))
                    ->create(['url_id' => $url->id]);
            });

        // A few expired / inactive URLs
        Url::factory()->count(3)->expired()->create(['user_id' => $user->id]);
        Url::factory()->count(2)->inactive()->create(['user_id' => $user->id]);

        $this->command->info('Seeded: demo@example.com | 10 active URLs with analytics data');
    }
}
