<?php

namespace App\Policies;

use App\Models\Url;
use App\Models\User;

class UrlPolicy
{
    /**
     * Authenticated users can only modify their own URLs.
     * Unauthenticated users can never modify any URL.
     */
    public function update(?User $user, Url $url): bool
    {
        return $user !== null && $url->user_id === $user->id;
    }

    public function delete(?User $user, Url $url): bool
    {
        return $user !== null && $url->user_id === $user->id;
    }

    public function viewStats(?User $user, Url $url): bool
    {
        // Stats are public by default (allows embedding in dashboards, etc.)
        // Flip to `$url->user_id === $user?->id` to make them private.
        return true;
    }
}
