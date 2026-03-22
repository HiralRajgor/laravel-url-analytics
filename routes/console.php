<?php

use App\Console\Commands\PurgeExpiredUrls;
use App\Console\Commands\SyncClickCounts;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
*/

// Flush Redis click counters to the DB every minute.
// withoutOverlapping() ensures we never run two syncs simultaneously.
Schedule::command(SyncClickCounts::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/click-sync.log'));

// Purge URLs expired more than 90 days ago — runs at 2am nightly.
Schedule::command(PurgeExpiredUrls::class, ['--days=90', '--no-interaction'])
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purge.log'));
