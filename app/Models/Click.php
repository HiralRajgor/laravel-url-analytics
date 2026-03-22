<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $url_id
 * @property string $ip_address
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $city
 * @property string|null $device_type desktop|mobile|tablet|bot
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $referrer
 * @property string|null $referrer_host
 * @property string|null $user_agent
 * @property bool $is_unique
 * @property Carbon $clicked_at
 */
class Click extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'url_id',
        'ip_address',
        'country_code',
        'country_name',
        'city',
        'device_type',
        'browser',
        'os',
        'referrer',
        'referrer_host',
        'user_agent',
        'is_unique',
        'clicked_at',
    ];

    protected $casts = [
        'is_unique'  => 'boolean',
        'clicked_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
