<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUrlRequest;
use App\Http\Requests\UpdateUrlRequest;
use App\Http\Resources\UrlResource;
use App\Http\Resources\UrlStatsResource;
use App\Models\Url;
use App\Services\Analytics\AnalyticsService;
use App\Services\Shortener\UrlShortenerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="URL Analytics API",
 *     description="Production-grade URL shortener with async geo-IP analytics, Redis caching, and rate limiting.",
 *
 *     @OA\Contact(email="hiralrajgor@yopmail.com"),
 *
 *     @OA\License(name="MIT")
 * )
 *
 * @OA\Server(url="/api/v1", description="V1 API")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 *
 * @OA\Tag(name="URLs", description="URL shortening and management")
 * @OA\Tag(name="Analytics", description="Click analytics and statistics")
 */
class UrlController extends Controller
{
    public function __construct(
        private readonly UrlShortenerService $shortener,
        private readonly AnalyticsService $analytics,
    ) {}

    // ─── GET /urls ─────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/urls",
     *     operationId="listUrls",
     *     tags={"URLs"},
     *     summary="List all shortened URLs",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="filter[is_active]", in="query", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(response=200, description="Paginated list of URLs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $urls = Url::query()
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->has('filter.is_active'), fn ($q) => $q->where('is_active', $request->input('filter.is_active')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UrlResource::collection($urls);
    }

    // ─── POST /urls ────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/urls",
     *     operationId="shortenUrl",
     *     tags={"URLs"},
     *     summary="Shorten a new URL",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoreUrlRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="URL shortened successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UrlResource")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Rate limit exceeded")
     * )
     */
    public function store(StoreUrlRequest $request): JsonResponse
    {
        $url = $this->shortener->shorten([
            'original_url' => $request->validated('original_url'),
            'custom_slug'  => $request->validated('custom_slug'),
            'title'        => $request->validated('title'),
            'expires_at'   => $request->validated('expires_at'),
            'user_id'      => $request->user()?->id,
        ]);

        return (new UrlResource($url))
            ->response()
            ->setStatusCode(201);
    }

    // ─── GET /urls/{shortCode} ─────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/urls/{shortCode}",
     *     operationId="showUrl",
     *     tags={"URLs"},
     *     summary="Get URL details",
     *
     *     @OA\Parameter(name="shortCode", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="URL details", @OA\JsonContent(ref="#/components/schemas/UrlResource")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Url $url): UrlResource
    {
        return new UrlResource($url);
    }

    // ─── PATCH /urls/{shortCode} ───────────────────────────────────────────────

    /**
     * @OA\Patch(
     *     path="/urls/{shortCode}",
     *     operationId="updateUrl",
     *     tags={"URLs"},
     *     summary="Update URL metadata",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="shortCode", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateUrlRequest")),
     *
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/UrlResource")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateUrlRequest $request, Url $url): UrlResource
    {
        $this->authorize('update', $url);

        $updated = $this->shortener->update($url, $request->validated());

        return new UrlResource($updated);
    }

    // ─── DELETE /urls/{shortCode} ──────────────────────────────────────────────

    /**
     * @OA\Delete(
     *     path="/urls/{shortCode}",
     *     operationId="deleteUrl",
     *     tags={"URLs"},
     *     summary="Delete a shortened URL",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="shortCode", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, Url $url): JsonResponse
    {
        $this->authorize('delete', $url);

        $this->shortener->delete($url);

        return response()->json(null, 204);
    }

    // ─── GET /urls/{shortCode}/stats ───────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/urls/{shortCode}/stats",
     *     operationId="getUrlStats",
     *     tags={"Analytics"},
     *     summary="Get click analytics for a URL",
     *
     *     @OA\Parameter(name="shortCode", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Analytics data", @OA\JsonContent(ref="#/components/schemas/UrlStatsResource")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function stats(Url $url): UrlStatsResource
    {
        $stats = $this->analytics->getStats($url);

        return new UrlStatsResource($stats);
    }
}
