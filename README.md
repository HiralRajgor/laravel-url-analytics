# 🔗 Laravel URL Analytics

[![CI](https://github.com/HiralRajgor/laravel-url-analytics/actions/workflows/ci.yml/badge.svg)](https://github.com/HiralRajgor/laravel-url-analytics/actions)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A production-grade URL shortener with async analytics, Redis-backed caching, atomic click counters, geo-IP enrichment, and a fully documented REST API.

Built as a deliberate architecture showcase: every design decision — from async job dispatch to Lua-script atomic drains — is the result of reasoning about scale, latency, and cost. The architecture is the interesting part.

---

## ✨ Feature Highlights

| Feature | Implementation |
|---|---|
| **URL shortening** | 7-char Base62 codes, collision-safe retry loop |
| **Custom slugs** | Validated against reserved words, uniqueness enforced at DB level |
| **Expiry support** | Per-URL TTL, `expires_at` checked on every redirect |
| **Redis caching** | Cache-first redirect resolution (< 5 ms on hit) |
| **Atomic click counters** | Redis `INCR` on redirect path, flushed to DB every minute |
| **Async geo-IP** | Dispatched to dedicated queue — zero redirect latency impact |
| **Unique click detection** | Redis `SET NX` with 1-hour dedup window |
| **Device detection** | UA parsing for device type, browser, OS |
| **Rate limiting** | Per-endpoint throttling (shorten: 10/min, redirect: 120/min) |
| **Full analytics** | Geo, device, referrer, time-series over 30 days |
| **Stats caching** | 5-min cache on stats aggregations — avoids N+1 on every poll |
| **Swagger docs** | Auto-generated OpenAPI 3.0 via `darkaonline/l5-swagger` |
| **Queue health** | Dedicated `analytics` queue, configurable worker concurrency |
| **Scheduler** | Click count sync (every minute), expired URL purge (nightly) |
| **Health check** | `/api/health` endpoint for k8s liveness/readiness probes |
| **CI pipeline** | GitHub Actions: parallel PHPUnit + Pint lint on every push |

---

## 🏗 Architecture Decisions

### Why async geo-IP lookup?

Inline geo-IP resolution adds 150–300 ms to every redirect response. Users clicking a short URL expect instant navigation — they have zero interest in analytics being recorded on that same request cycle. By dispatching a `ProcessClickAnalytics` job to a dedicated `analytics` queue, the redirect controller returns a `301` in under 5 ms on a cache hit, or under 20 ms on a DB fallback. If the geo-IP provider is slow or starts rate-limiting us, only the queue backs up — not user-facing redirects. This is the kind of latency isolation that matters at real traffic volumes.

### Redis atomic click counting

Every redirect atomically increments a Redis counter (`INCR url_counter:{code}`) in roughly 1 µs. A scheduled command runs every minute and batch-flushes all dirty counters to MySQL using a single `UPDATE ... CASE WHEN` statement. This means:

- **Zero DB writes on the redirect hot path**
- **`click_count` is eventually consistent** (max 60 s lag — fine for analytics)
- **The drain is a Lua script** (`GET` + `DEL` atomically) to prevent double-counting under concurrency

### Cache key design

```
url:{short_code}           → serialised Url model   (TTL: 1 hr)
url_stats:{short_code}     → aggregated stats array  (TTL: 5 min)
unique_click:{code}:{ip}   → dedup flag              (TTL: 1 hr)
click_counter:{code}       → atomic INCR counter     (no TTL — drained by scheduler)
```

Stats are busted by the analytics job on every new click write — so a poll-heavy dashboard gets stale-but-fast data, while a fresh page load after a burst of clicks is never more than 5 minutes behind.

### Service layer design

```
RedirectController          — lean, cache-first, dispatches job
UrlShortenerService         — code generation + CRUD
UrlCacheService             — all Redis interactions centralised
GeoIpService                — injectable HTTP client wrapper (easy to mock)
DeviceDetectionService      — stateless UA parser
AnalyticsService            — aggregation queries, cache-aware
ProcessClickAnalytics (Job) — enrichment pipeline, 3 retries, exp backoff
SyncClickCounts (Command)   — atomic counter drain, overlap-safe
```

No logic lives in controllers. No HTTP calls in the redirect path.

---

## 🚀 Quick Start

### Option A — Docker (recommended)

```bash
git clone https://github.com/yourname/laravel-url-analytics.git
cd laravel-url-analytics

cp .env.example .env

docker compose up -d

# App available at http://localhost:8000
# Swagger UI at  http://localhost:8000/api/documentation
# Redis UI at    http://localhost:8081  (run with --profile debug)
```

### Option B — Local PHP

**Requirements:** PHP 8.3+, Composer, Redis, MySQL 8+ (or SQLite for dev)

```bash
git clone https://github.com/yourname/laravel-url-analytics.git
cd laravel-url-analytics

composer install

cp .env.example .env
# Edit .env — set DB_* and REDIS_* values

php artisan key:generate
php artisan migrate --seed

# Terminal 1: web server
php artisan serve

# Terminal 2: queue worker (analytics queue must run separately)
php artisan queue:work redis --queue=analytics,default --tries=3

# Terminal 3: scheduler (or add to crontab)
php artisan schedule:work
```

---

## 🔑 Authentication

The API uses Laravel Sanctum token authentication. Public endpoints (shorten, redirect, stats) require no token. Mutations (update, delete) require a Bearer token.

```bash
# Create a token via Tinker
php artisan tinker
>>> $user = \App\Models\User::first();
>>> $user->createToken('my-app')->plainTextToken;
```

```bash
# Use in requests
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/v1/urls
```

---

## 📡 API Reference

Full interactive docs at `/api/documentation` after running `php artisan l5-swagger:generate`.

### Shorten a URL

```http
POST /api/v1/urls
Content-Type: application/json

{
  "original_url": "https://example.com/very/long/marketing/path",
  "custom_slug": "summer-sale",
  "title": "Summer Sale Campaign",
  "expires_at": "2025-12-31T23:59:59Z"
}
```

```json
{
  "data": {
    "id": 1,
    "short_code": "summer-sale",
    "short_url": "http://localhost/summer-sale",
    "original_url": "https://example.com/very/long/marketing/path",
    "title": "Summer Sale Campaign",
    "click_count": 0,
    "is_active": true,
    "is_expired": false,
    "expires_at": "2025-12-31T23:59:59+00:00",
    "created_at": "2025-03-22T10:00:00+00:00",
    "_links": {
      "self": "http://localhost/api/v1/urls/summer-sale",
      "stats": "http://localhost/api/v1/urls/summer-sale/stats",
      "redirect": "http://localhost/summer-sale"
    }
  }
}
```

### Get Analytics

```http
GET /api/v1/urls/summer-sale/stats
```

```json
{
  "data": {
    "url_id": 1,
    "short_code": "summer-sale",
    "total_clicks": 1482,
    "unique_clicks": 934,
    "clicks_over_time": [
      { "date": "2025-03-15", "clicks": 87 },
      { "date": "2025-03-16", "clicks": 214 }
    ],
    "top_countries": [
      { "label": "United Kingdom", "count": 402 },
      { "label": "United States", "count": 388 }
    ],
    "device_breakdown": [
      { "label": "mobile",  "count": 891 },
      { "label": "desktop", "count": 541 }
    ],
    "top_referrers": [
      { "label": "twitter.com",  "count": 310 },
      { "label": "linkedin.com", "count": 197 }
    ],
    "computed_at": "2025-03-22T10:05:00+00:00"
  }
}
```

### Redirect

```http
GET /summer-sale
→ 301 Location: https://example.com/very/long/marketing/path
```

### All Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/urls` | Optional | Shorten a URL |
| `GET` | `/api/v1/urls` | Required | List your URLs |
| `GET` | `/api/v1/urls/{code}` | — | Get URL detail |
| `PATCH` | `/api/v1/urls/{code}` | Required | Update title/status/expiry |
| `DELETE` | `/api/v1/urls/{code}` | Required | Delete a URL |
| `GET` | `/api/v1/urls/{code}/stats` | — | Get click analytics |
| `GET` | `/api/health` | — | Service health check |
| `GET` | `/{code}` | — | Redirect to original URL |

---

## 🧪 Testing

```bash
# Full test suite
php artisan test

# Parallel execution (faster on multi-core)
php artisan test --parallel

# With coverage report
php artisan test --coverage

# Run a specific suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run a single test file
php artisan test tests/Feature/Api/RedirectTest.php
```

### Test design philosophy

- **Unit tests** mock all I/O (Redis, HTTP, DB) — fast and deterministic
- **Feature tests** use SQLite `:memory:` + real Redis DB 15 — end-to-end without Docker
- **HTTP mocking** in `GeoIpServiceTest` via `Http::fake()` — no external calls in CI
- **Queue::fake()** in redirect tests — assert jobs dispatched without running them
- **No fixtures/snapshots** — factories generate contextually meaningful data

---

## ⚙️ Artisan Commands

```bash
# Flush Redis click counters → DB (runs automatically every minute via scheduler)
php artisan urls:sync-click-counts

# Dry run — preview what would be updated
php artisan urls:sync-click-counts --dry-run

# Purge URLs expired more than 90 days ago
php artisan urls:purge-expired

# Custom grace period
php artisan urls:purge-expired --days=30
```

---

## 📐 Database Schema

```
urls
├── id                   bigint PK
├── user_id              bigint FK → users.id (nullable)
├── original_url         varchar(2048)
├── short_code           varchar(64) UNIQUE
├── title                varchar(255) nullable
├── custom_slug          varchar(64) nullable
├── click_count          bigint default 0
├── is_active            boolean default true
├── expires_at           timestamp nullable
├── created_at / updated_at

clicks (append-only, no updated_at)
├── id                   bigint PK
├── url_id               bigint FK → urls.id CASCADE DELETE
├── ip_address           varchar(45)
├── country_code         char(2) nullable
├── country_name         varchar(100) nullable
├── city                 varchar(100) nullable
├── device_type          varchar(20) nullable  [desktop|mobile|tablet|bot]
├── browser              varchar(50) nullable
├── os                   varchar(50) nullable
├── referrer             varchar(2048) nullable
├── referrer_host        varchar(255) nullable
├── user_agent           varchar(512) nullable
├── is_unique            boolean
└── clicked_at           timestamp (indexed)
```

Indexes on `clicks`: `(url_id, clicked_at)`, `(url_id, is_unique)`, `(url_id, country_name)`, `(url_id, device_type)` — covering the four most common analytics aggregation patterns.

---

## ⚡ Performance Characteristics

| Operation | Latency | Notes |
|---|---|---|
| Redirect (cache hit) | < 5 ms | Redis lookup + 1 µs INCR + job dispatch |
| Redirect (cache miss) | < 25 ms | DB query + cache warm + INCR + dispatch |
| Shorten URL | < 30 ms | Unique code gen + DB insert + cache warm |
| Stats (cached) | < 10 ms | Redis key lookup |
| Stats (cold) | 50–200 ms | 6 aggregation queries on `clicks` table |
| Geo-IP enrichment | 150–300 ms | **Off the redirect path** — runs in worker |

---

## 🔧 Configuration Reference

All settings live in `config/url-shortener.php`, driven by `.env`:

| Key | Default | Description |
|-----|---------|-------------|
| `URL_SHORTENER_CODE_LENGTH` | `7` | Short code length (7 = 62^7 ≈ 3.5T combos) |
| `URL_SHORTENER_DEFAULT_EXPIRY_DAYS` | `365` | Default TTL when none specified |
| `RATE_LIMIT_SHORTEN` | `10` | Max shorten requests per minute per IP |
| `RATE_LIMIT_REDIRECT` | `120` | Max redirect requests per minute per IP |
| `GEO_IP_PROVIDER` | `ipapi` | Geo-IP backend (ipapi.co free tier: 1000/day) |
| `ANALYTICS_QUEUE` | `analytics` | Queue name for click enrichment jobs |

---

## ⚠️ Known Limitations

- **Geo-IP free tier**: ipapi.co allows 1,000 requests/day on the free plan. The queue rate-limiter (`geo-ip-lookup`, 1 req/s) enforces this. For higher volume, swap `GeoIpService` for MaxMind GeoIP2 with a local database — the interface is identical, just the provider changes.

- **`click_count` is eventually consistent**: The Redis counter is flushed to MySQL every 60 seconds. Real-time dashboards should read `click_count` from Redis directly, not from the DB column. The `/stats` endpoint aggregates from the `clicks` table (accurate) not `click_count` (lagging).

- **No user registration UI**: This is a headless API. Authentication is Sanctum token-based — create tokens via `php artisan tinker` or build your own registration endpoint.

- **Single-region geo-IP**: Clicks from private/reserved IPs (localhost, RFC1918) are recorded without geo data. Expected in development.

- **No link preview / OG metadata fetching**: `title` is user-supplied. Auto-fetching the `<title>` tag from the destination URL is a natural extension (add it as a queued job).

---

## 🗺 Future Scope

- [ ] **MaxMind GeoIP2 local DB** — eliminate the external HTTP call entirely, sub-millisecond resolution, no rate limits
- [ ] **QR code generation** — `endroid/qr-code` package, cached as object storage asset
- [ ] **Password-protected links** — `password_hash` on `urls` table, challenge middleware
- [ ] **UTM parameter passthrough** — append `?utm_source=...` to destination URL automatically
- [ ] **Webhook on click** — dispatch `ClickRecorded` event for real-time dashboard integration
- [ ] **Bulk URL import** — CSV upload → queued `ProcessBulkImport` job
- [ ] **Teams / workspaces** — multi-tenant URL ownership beyond single user
- [ ] **API rate limiting by plan** — tiered limits (free/pro/enterprise) tied to user model
- [ ] **ClickHouse analytics backend** — replace MySQL aggregation queries with a columnar store optimised for append-only time-series data at scale
- [ ] **Edge caching** — Cloudflare Worker that intercepts redirects at the CDN edge before the request reaches Laravel at all

---

## 📁 Project Structure

```
app/
├── Console/Commands/
│   ├── SyncClickCounts.php       # Redis → DB counter flush
│   └── PurgeExpiredUrls.php      # Nightly cleanup
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── RedirectController.php    # Hot path: cache → redirect → dispatch
│   │   ├── UrlController.php         # CRUD + stats
│   │   └── HealthCheckController.php # k8s probe
│   ├── Requests/
│   │   ├── StoreUrlRequest.php
│   │   └── UpdateUrlRequest.php
│   └── Resources/
│       ├── UrlResource.php
│       └── UrlStatsResource.php
├── Jobs/
│   └── ProcessClickAnalytics.php  # Async geo + UA enrichment
├── Models/
│   ├── Url.php
│   ├── Click.php
│   └── User.php
├── Policies/
│   └── UrlPolicy.php
├── Providers/
│   └── AppServiceProvider.php    # DI bindings + rate limiter config
└── Services/
    ├── Analytics/
    │   ├── AnalyticsService.php      # Aggregation queries
    │   ├── DeviceDetectionService.php
    │   └── GeoIpService.php          # HTTP client wrapper
    ├── Cache/
    │   └── UrlCacheService.php       # All Redis ops centralised
    └── Shortener/
        └── UrlShortenerService.php   # Code gen + CRUD

tests/
├── Feature/
│   ├── Api/
│   │   ├── UrlShortenTest.php
│   │   ├── RedirectTest.php
│   │   ├── AnalyticsStatsTest.php
│   │   └── HealthCheckTest.php
│   └── Console/
│       └── SyncClickCountsTest.php
└── Unit/
    ├── Jobs/
    │   └── ProcessClickAnalyticsTest.php
    └── Services/
        ├── GeoIpServiceTest.php
        └── UrlCacheServiceTest.php
```

---

## 📄 License

MIT — see [LICENSE](LICENSE).

---

<p align="center">Built with ☕ and deliberate engineering choices.</p>
