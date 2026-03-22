<?php

namespace App\Console\Commands;

use App\Models\Url;
use App\Services\Cache\UrlCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drains Redis atomic click counters into the urls.click_count column.
 *
 * Run via scheduler every minute:
 *   $schedule->command('urls:sync-click-counts')->everyMinute()->withoutOverlapping();
 *
 * Why two storage locations?
 * Redis receives every click as an atomic INCR (< 1µs, no DB write).
 * This command batches the flush to MySQL/SQLite, keeping click_count
 * accurate without hammering the DB on every redirect.
 */
class SyncClickCounts extends Command
{
    protected $signature = 'urls:sync-click-counts {--dry-run : Show what would be updated without writing}';

    protected $description = 'Flush Redis click counters into the urls table';

    public function handle(UrlCacheService $cache): int
    {
        $isDryRun = $this->option('dry-run');
        $updated  = 0;

        // Only process URLs that have a pending counter in Redis
        Url::whereNotNull('short_code')->chunk(200, function ($urls) use ($cache, $isDryRun, &$updated) {
            $cases  = [];
            $ids    = [];
            $params = [];

            foreach ($urls as $url) {
                $increment = $cache->drainClickCount($url->short_code);

                if ($increment <= 0) {
                    continue;
                }

                $this->line("  [{$url->short_code}] +{$increment} clicks");
                $cases[] = "WHEN id = {$url->id} THEN click_count + {$increment}";
                $ids[]   = $url->id;
                $updated++;
            }

            if (! empty($ids) && ! $isDryRun) {
                DB::statement(
                    'UPDATE urls SET click_count = CASE '.implode(' ', $cases).' END WHERE id IN ('.implode(',', $ids).')',
                );
            }
        });

        $action = $isDryRun ? 'Would update' : 'Updated';
        $this->info("{$action} {$updated} URL(s).");

        return self::SUCCESS;
    }
}
