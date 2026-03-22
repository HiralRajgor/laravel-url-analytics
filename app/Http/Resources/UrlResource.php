<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UrlResource",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="short_code", type="string", example="abc1234"),
 *     @OA\Property(property="short_url", type="string", example="https://sho.rt/abc1234"),
 *     @OA\Property(property="original_url", type="string"),
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="click_count", type="integer"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class UrlResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'short_code'   => $this->short_code,
            'short_url'    => $this->short_url,
            'original_url' => $this->original_url,
            'title'        => $this->title,
            'click_count'  => $this->click_count,
            'is_active'    => $this->is_active,
            'is_expired'   => $this->isExpired(),
            'expires_at'   => $this->expires_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),

            // Links block — makes it easy for API consumers to navigate
            '_links' => [
                'self'     => url("/api/v1/urls/{$this->short_code}"),
                'stats'    => url("/api/v1/urls/{$this->short_code}/stats"),
                'redirect' => url("/{$this->short_code}"),
            ],
        ];
    }
}
