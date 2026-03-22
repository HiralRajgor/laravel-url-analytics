<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateUrlRequest",
 *
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
 * )
 */
class UpdateUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles ownership check
    }

    public function rules(): array
    {
        return [
            'title'      => ['nullable', 'string', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
