<?php

namespace Tests\Feature\Api;

use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlShortenTest extends TestCase
{
    use RefreshDatabase;

    // ─── POST /api/v1/urls ─────────────────────────────────────────────────

    public function test_guest_can_shorten_a_url(): void
    {
        $response = $this->postJson('/api/v1/urls', [
            'original_url' => 'https://example.com/some/long/path',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'short_code', 'short_url',
                    'original_url', 'click_count', 'is_active',
                    '_links' => ['self', 'stats', 'redirect'],
                ],
            ])
            ->assertJsonPath('data.original_url', 'https://example.com/some/long/path')
            ->assertJsonPath('data.click_count', 0)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('urls', [
            'original_url' => 'https://example.com/some/long/path',
        ]);
    }

    public function test_can_shorten_with_custom_slug(): void
    {
        $response = $this->postJson('/api/v1/urls', [
            'original_url' => 'https://example.com',
            'custom_slug'  => 'my-brand',
            'title'        => 'My Brand Link',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.short_code', 'my-brand');

        $this->assertDatabaseHas('urls', ['short_code' => 'my-brand']);
    }

    public function test_duplicate_custom_slug_returns_422(): void
    {
        Url::factory()->withCustomSlug('taken')->create();

        $this->postJson('/api/v1/urls', [
            'original_url' => 'https://example.com',
            'custom_slug'  => 'taken',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['custom_slug']);
    }

    public function test_invalid_url_returns_422(): void
    {
        $this->postJson('/api/v1/urls', [
            'original_url' => 'not-a-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['original_url']);
    }

    public function test_self_referential_url_is_rejected(): void
    {
        config(['url-shortener.domain' => 'http://localhost']);

        $this->postJson('/api/v1/urls', [
            'original_url' => 'http://localhost/abc1234',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['original_url']);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this->postJson('/api/v1/urls', [
            'original_url' => 'https://example.com',
            'custom_slug'  => 'api',
        ])->assertStatus(422);
    }

    // ─── GET /api/v1/urls ─────────────────────────────────────────────────

    public function test_authenticated_user_sees_only_their_urls(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Url::factory()->count(3)->create(['user_id' => $user->id]);
        Url::factory()->count(5)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/urls')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // ─── PATCH /api/v1/urls/{code} ─────────────────────────────────────────

    public function test_owner_can_update_url(): void
    {
        $user = User::factory()->create();
        $url  = Url::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$url->short_code}", ['title' => 'New Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title');
    }

    public function test_non_owner_cannot_update_url(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $url   = Url::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->patchJson("/api/v1/urls/{$url->short_code}", ['title' => 'Hijack'])
            ->assertForbidden();
    }

    // ─── DELETE /api/v1/urls/{code} ────────────────────────────────────────

    public function test_owner_can_delete_url(): void
    {
        $user = User::factory()->create();
        $url  = Url::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/urls/{$url->short_code}")
            ->assertNoContent();

        $this->assertDatabaseMissing('urls', ['id' => $url->id]);
    }

    // ─── Rate limiting ─────────────────────────────────────────────────────

    public function test_shorten_endpoint_is_rate_limited(): void
    {
        config(['url-shortener.rate_limits.shorten' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/urls', ['original_url' => 'https://example.com'])
                ->assertStatus(201);
        }

        $this->postJson('/api/v1/urls', ['original_url' => 'https://example.com'])
            ->assertStatus(429);
    }
}
