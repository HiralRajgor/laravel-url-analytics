<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UrlStatsResource",
 *
 *     @OA\Property(property="url_id", type="integer"),
 *     @OA\Property(property="short_code", type="string"),
 *     @OA\Property(property="short_url", type="string"),
 *     @OA\Property(property="total_clicks", type="integer"),
 *     @OA\Property(property="unique_clicks", type="integer"),
 *     @OA\Property(property="clicks_over_time", type="array", @OA\Items(
 *         @OA\Property(property="date", type="string", format="date"),
 *         @OA\Property(property="clicks", type="integer")
 *     )),
 *     @OA\Property(property="top_countries", type="array", @OA\Items(
 *         @OA\Property(property="label", type="string"),
 *         @OA\Property(property="count", type="integer")
 *     )),
 *     @OA\Property(property="device_breakdown", type="array", @OA\Items(
 *         @OA\Property(property="label", type="string"),
 *         @OA\Property(property="count", type="integer")
 *     )),
 *     @OA\Property(property="computed_at", type="string", format="date-time")
 * )
 */
class UrlStatsResource extends JsonResource
{
    /** $resource is already a plain array from AnalyticsService */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
