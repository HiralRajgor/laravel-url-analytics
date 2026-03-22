<?php

namespace App\Services\Shortener;

use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Core URL shortening logic.
 *
 * Responsibility: generate unique codes, persist URLs, handle custom slugs.
 * Cache invalidation is delegated to UrlCacheService.
 */
final class UrlShortenerService
{
    public function __construct(
        private readonly UrlCacheService $cache,
    ) {}

    /**
     * Create a new shortened URL.
     *
     * @param  array{
     *   original_url:   string,
     *   custom_slug?:   string|null,
     *   title?:         string|null,
     *   expires_at?:    \DateTimeInterface|null,
     *   user_id?:       int|null,
     * } $data
     */
    public function shorten(array $data): Url
    {
        $shortCode = $data['custom_slug']
            ? $this->resolveCustomSlug($data['custom_slug'])
            : $this->generateUniqueCode();

        $url = DB::transaction(function () use ($data, $shortCode): Url {
            return Url::create([
                'user_id'      => $data['user_id'] ?? null,
                'original_url' => $data['original_url'],
                'short_code'   => $shortCode,
                'title'        => $data['title']       ?? null,
                'custom_slug'  => $data['custom_slug'] ?? null,
                'expires_at'   => $data['expires_at']  ?? now()->addDays(
                    config('url-shortener.default_expiry_days'),
                ),
                'is_active'   => true,
                'click_count' => 0,
            ]);
        });

        // Warm the cache immediately so the first redirect is cache-hit
        $this->cache->put($url);

        return $url;
    }

    /**
     * Update an existing URL's metadata.
     */
    public function update(Url $url, array $data): Url
    {
        $url->update(array_filter($data, fn ($v) => $v !== null));
        $this->cache->forget($url->short_code);
        $this->cache->put($url->fresh());

        return $url;
    }

    /**
     * Soft-deactivate a URL (keeps analytics intact).
     */
    public function deactivate(Url $url): void
    {
        $url->update(['is_active' => false]);
        $this->cache->forget($url->short_code);
    }

    /**
     * Hard-delete a URL and all associated clicks.
     */
    public function delete(Url $url): void
    {
        $this->cache->forget($url->short_code);
        $url->delete();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function generateUniqueCode(): string
    {
        $length   = config('url-shortener.code_length', 7);
        $reserved = config('url-shortener.reserved_codes', []);

        $attempts = 0;

        do {
            if (++$attempts > 10) {
                throw new RuntimeException('Unable to generate a unique short code after 10 attempts.');
            }

            // Base62 URL-safe alphabet (avoids ambiguous chars like 0/O, 1/l)
            $code = Str::random($length);
        } while (
            in_array($code, $reserved, true) || Url::where('short_code', $code)->exists()
        );

        return $code;
    }

    private function resolveCustomSlug(string $slug): string
    {
        $reserved = config('url-shortener.reserved_codes', []);

        if (in_array(strtolower($slug), $reserved, true)) {
            throw new \InvalidArgumentException("The slug '{$slug}' is reserved.");
        }

        if (Url::where('short_code', $slug)->exists()) {
            throw new \InvalidArgumentException("The slug '{$slug}' is already taken.");
        }

        return $slug;
    }
}
