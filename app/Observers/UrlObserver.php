<?php

namespace App\Observers;

use App\Models\Url;
use Illuminate\Support\Facades\Log;

/**
 * Url model observer.
 *
 * Currently used for audit logging. Future extensions:
 *  - Fire UrlCreated event for webhook delivery
 *  - Notify user when their URL is about to expire
 *  - Sync to a search index (Meilisearch/Algolia)
 */
class UrlObserver
{
    public function created(Url $url): void
    {
        Log::info('URL created', [
            'id'           => $url->id,
            'short_code'   => $url->short_code,
            'user_id'      => $url->user_id,
            'original_url' => $url->original_url,
        ]);
    }

    public function updated(Url $url): void
    {
        if ($url->wasChanged('is_active')) {
            Log::info('URL active status changed', [
                'id'         => $url->id,
                'short_code' => $url->short_code,
                'is_active'  => $url->is_active,
            ]);
        }
    }

    public function deleted(Url $url): void
    {
        Log::info('URL deleted', [
            'id'         => $url->id,
            'short_code' => $url->short_code,
        ]);
    }
}
