<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="StoreUrlRequest",
 *     required={"original_url"},
 *
 *     @OA\Property(property="original_url", type="string", format="url", example="https://example.com/very/long/path"),
 *     @OA\Property(property="custom_slug", type="string", example="my-link", nullable=true),
 *     @OA\Property(property="title", type="string", example="My Campaign Link", nullable=true),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
 * )
 */
class StoreUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required to shorten
    }

    public function rules(): array
    {
        return [
            'original_url' => [
                'required',
                'string',
                'url',
                'max:2048',
                // Prevent shortening our own short URLs (open redirect loop)
                function (string $attribute, mixed $value, \Closure $fail) {
                    $domain    = parse_url(config('url-shortener.domain'), PHP_URL_HOST);
                    $inputHost = parse_url($value, PHP_URL_HOST);
                    if ($domain && $inputHost && str_contains($inputHost, $domain)) {
                        $fail('You cannot shorten a URL from this domain.');
                    }
                },
            ],
            'custom_slug' => [
                'nullable',
                'string',
                'min:3',
                'max:64',
                'regex:/^[a-zA-Z0-9_-]+$/',
                'unique:urls,short_code',
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'custom_slug.regex'  => 'The custom slug may only contain letters, numbers, hyphens, and underscores.',
            'custom_slug.unique' => 'This custom slug is already taken.',
            'expires_at.after'   => 'The expiry date must be in the future.',
        ];
    }
}
