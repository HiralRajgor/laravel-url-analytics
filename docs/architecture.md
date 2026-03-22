# Architecture Deep Dive

This document explains the key architectural decisions in `laravel-url-analytics` — the *why* behind the code, not just the *what*.

---

## Request Lifecycle

### Redirect (hot path)

```
Browser                  Laravel                  Redis                Queue Worker
   │                        │                        │                       │
   │  GET /{code}           │                        │                       │
   │───────────────────────>│                        │                       │
   │                        │  GET url:{code}        │                       │
   │                        │───────────────────────>│                       │
   │                        │  <── Url model (1ms)   │                       │
   │                        │                        │                       │
   │                        │  INCR click_counter    │                       │
   │                        │───────────────────────>│                       │
   │                        │  <── new count (1µs)   │                       │
   │                        │                        │                       │
   │                        │  dispatch(ProcessClickAnalytics) ─────────────>│
   │                        │  [non-blocking]                                │
   │                        │                                                │
   │  301 Location: ...     │                                                │
   │<───────────────────────│                                                │
   │                        │                        │  resolve geo-IP       │
   │                        │                        │  parse UA             │
   │                        │                        │  INSERT clicks        │
   │                        │                        │  FORGET url_stats     │
```

Total redirect latency on cache hit: **< 5 ms**. The user is gone before the analytics job even starts.

---

## Redis Key Design

```
Namespace         Key pattern                    TTL          Purpose
─────────────────────────────────────────────────────────────────────────────
url               url:{short_code}               3600s        Serialised Url model for redirect resolution
stats             url_stats:{short_code}         300s         Aggregated stats array for /stats endpoint
unique            unique_click:{code}:{ip_hash}  3600s        SETNX flag — 1-hour unique-click dedup window
counter           click_counter:{short_code}     none         Atomic INCR counter, drained by scheduler
```

### Why a separate click counter?

The alternative is to do `UPDATE urls SET click_count = click_count + 1 WHERE id = ?` on every redirect. At 1,000 req/s this is:

- 1,000 DB write transactions per second
- Each one acquiring a row-level lock
- Potential for lock contention under burst traffic

The Redis counter approach converts that to:

- 1,000 × 1µs Redis `INCR` calls (effectively free)
- 1 batched `UPDATE ... CASE WHEN` per minute

The tradeoff is that `click_count` is eventually consistent with a max 60-second lag. This is entirely acceptable for analytics — the `/stats` endpoint aggregates from the `clicks` table anyway, which is always accurate.

### The Lua drain script

Naively draining the counter looks like:

```php
$count = Redis::get($key);  // (1) read
Redis::del($key);           // (2) delete
return $count;
```

The race condition: if a redirect happens between (1) and (2), that increment is lost. The Lua script runs atomically:

```lua
local v = redis.call('GET', KEYS[1])
if v then
  redis.call('DEL', KEYS[1])
  return tonumber(v)
else
  return 0
end
```

Redis executes Lua scripts as a single atomic operation — no other command can run between `GET` and `DEL`.

---

## Queue Architecture

### Why a dedicated `analytics` queue?

```
Queue: analytics    → ProcessClickAnalytics jobs (geo-IP, UA parse, DB write)
Queue: default      → all other jobs
```

Running analytics on a dedicated queue means:

1. **Isolation**: A backlog of geo-IP jobs (e.g. provider is slow) doesn't delay other queued work
2. **Tunable workers**: You can run 2 `default` workers and 8 `analytics` workers independently
3. **Priority**: `queue:work redis --queue=default,analytics` — default jobs always run first

Start the worker with:

```bash
php artisan queue:work redis --queue=analytics,default --sleep=3 --tries=3
```

### Job retry strategy

`ProcessClickAnalytics` has:

```php
public int $tries  = 3;
public int $backoff = 10; // seconds — Laravel doubles each retry (10s, 20s, 40s)
```

If all retries fail (e.g. geo-IP provider is down for hours), the job lands in `failed_jobs`. The click is still recorded by the `INCR` counter and will appear in `click_count` — only the enrichment data (country, device) is missing. Analytics data loss is bounded to the enrichment layer.

---

## Database Index Strategy

The `clicks` table has four composite indexes, each covering one of the aggregation patterns in `AnalyticsService`:

```sql
INDEX (url_id, clicked_at)    -- clicks_over_time: WHERE url_id = ? AND clicked_at >= ?
INDEX (url_id, is_unique)     -- unique_clicks count
INDEX (url_id, country_name)  -- top_countries GROUP BY
INDEX (url_id, device_type)   -- device_breakdown GROUP BY
```

Without these, every stats request would do a full `clicks` table scan filtered by `url_id`. With them, each aggregation query is an index range scan.

The `clicks` table has no `updated_at` column — clicks are immutable append-only records. This is intentional: it makes the table partitionable by date in future if row counts grow to hundreds of millions.

---

## Service Dependency Graph

```
RedirectController
    └── UrlCacheService          (Redis ops)
    └── ProcessClickAnalytics    (Job — dispatched, not injected)

UrlController
    └── UrlShortenerService
    │       └── UrlCacheService
    └── AnalyticsService
            └── UrlCacheService

ProcessClickAnalytics (Job)
    └── GeoIpService             (HTTP client — mockable)
    └── DeviceDetectionService   (pure, no I/O)
    └── UrlCacheService
```

`GeoIpService` accepts an `Illuminate\Http\Client\Factory` via constructor injection. In tests, `Http::fake()` intercepts calls without any mocking framework. In production, the real HTTP factory makes live requests.

---

## Scaling Considerations

### Horizontal scaling

The application is stateless — no in-memory session state. Multiple app instances behind a load balancer will work correctly as long as:

- All instances share the same Redis instance (or Redis Cluster)
- All instances share the same MySQL instance (or read replica + single writer)

### Click counter at scale

At very high traffic (> 10k req/s per URL), the scheduler's once-per-minute drain will produce `click_count` values that are 60 seconds behind at most. This is acceptable. If you need real-time `click_count`, read from Redis directly:

```php
$liveCount = $url->click_count + $cache->peekClickCount($url->short_code);
```

### Stats at scale

`AnalyticsService` runs 6 aggregation queries against `clicks`. At 100M rows, these will slow down even with indexes. The natural upgrade path is:

1. Add a `click_aggregates` materialized view or summary table, updated by the analytics job
2. Or migrate the `clicks` table to ClickHouse, which is purpose-built for this query pattern

---

## Security Considerations

### Open redirect prevention

`StoreUrlRequest` validates that the `original_url` domain doesn't match the shortener's own domain. This prevents using the shortener as a proxy to launder our own domain reputation.

### Rate limiting

Two separate limiters:

```
shorten:  10/min per IP (or user ID if authenticated) — prevents URL spam
redirect: 120/min per IP — prevents short-code enumeration attacks
```

Both are configurable via `.env`. The redirect limit is intentionally high — blocking legitimate users from following a link is worse than a scanner enumerating a few codes.

### IP hashing for uniqueness

The unique-click Redis key uses `md5($ip)` as the hash — we store the hash, not the raw IP, in the Redis key name. The raw IP is stored in the `clicks` table for analytics. This prevents Redis key injection if an attacker crafts a pathological IP string.
