<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Console\Command;

/**
 * Permanently removes expired URLs and their click records.
 * Runs nightly via scheduler.
 */
class PurgeExpiredUrls extends Command
{
    protected $signature = 'urls:purge-expired {--days=90 : Only purge URLs expired more than N days ago}';

    protected $description = 'Permanently delete expired URL records';

    public function handle(UrlCacheService $cache): int
    {
        $days   = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $query = Url::where('expires_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired URLs to purge.');

            return self::SUCCESS;
        }

        if (! $this->confirm("About to permanently delete {$count} expired URL(s). Continue?")) {
            return self::SUCCESS;
        }

        $query->chunk(100, function ($urls) use ($cache) {
            foreach ($urls as $url) {
                $cache->forget($url->short_code);
            }

            // Delete associated clicks first (no cascade needed, handle explicitly)
            $ids = $urls->pluck('id');
            Click::whereIn('url_id', $ids)->delete();
            Url::whereIn('id', $ids)->delete();
        });

        $this->info("Purged {$count} expired URL(s).");

        return self::SUCCESS;
    }
}
