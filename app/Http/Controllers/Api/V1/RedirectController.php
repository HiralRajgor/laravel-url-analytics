<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessClickAnalytics;
use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(name="Redirect", description="URL redirect endpoint")
 */
class RedirectController extends Controller
{
    public function __construct(
        private readonly UrlCacheService $cache,
    ) {}

    /**
     * @OA\Get(
     *     path="/{shortCode}",
     *     operationId="redirect",
     *     tags={"Redirect"},
     *     summary="Redirect to original URL",
     *     description="Resolves a short code and returns a 301 redirect. Analytics are processed asynchronously.",
     *
     *     @OA\Parameter(
     *         name="shortCode",
     *         in="path",
     *         required=true,
     *         description="The short code to resolve",
     *
     *         @OA\Schema(type="string", example="abc1234")
     *     ),
     *
     *     @OA\Response(
     *         response=301,
     *         description="Redirect to original URL",
     *
     *         @OA\Header(header="Location", description="The original URL", @OA\Schema(type="string"))
     *     ),
     *
     *     @OA\Response(response=404, description="Short URL not found or expired")
     * )
     */
    public function __invoke(Request $request, string $shortCode): Response
    {
        // 1. Cache-first lookup (< 1ms)
        $url = $this->cache->get($shortCode);

        // 2. DB fallback
        if ($url === null) {
            $url = Url::where('short_code', $shortCode)->first();

            if ($url === null) {
                return response()->json(['message' => 'Short URL not found.'], 404);
            }

            // Warm cache for subsequent hits
            if ($url->isAccessible()) {
                $this->cache->put($url);
            }
        }

        if (! $url->isAccessible()) {
            return response()->json([
                'message' => $url->isExpired() ? 'This short URL has expired.' : 'This short URL is inactive.',
            ], 410);
        }

        // 3. Atomically bump the Redis counter (non-blocking, ~1µs)
        $this->cache->incrementClickCount($shortCode);

        // 4. Dispatch geo-IP + UA enrichment to the analytics queue (non-blocking)
        ProcessClickAnalytics::dispatch(
            url: $url,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
            referrer: $request->headers->get('referer'),
        )->onQueue(config('url-shortener.analytics_queue'));

        // 5. Return 301 immediately — user is on their way
        return redirect()->away($url->original_url, 301);
    }
}
