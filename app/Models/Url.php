<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $original_url
 * @property string $short_code
 * @property string|null $title
 * @property string|null $custom_slug
 * @property int $click_count
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Url extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_url',
        'short_code',
        'title',
        'custom_slug',
        'click_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'is_active'   => 'boolean',
        'click_count' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getShortUrlAttribute(): string
    {
        return rtrim(config('url-shortener.domain'), '/').'/'.$this->short_code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }
}
