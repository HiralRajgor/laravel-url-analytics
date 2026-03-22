# Contributing

Contributions, issues, and feature requests are welcome. This document explains how the project is structured and what standards PRs must meet.

---

## Development Setup

```bash
git clone https://github.com/yourname/laravel-url-analytics.git
cd laravel-url-analytics

# Option A: Docker (zero local dependencies beyond Docker)
make up

# Option B: Local PHP 8.3
make install && make key && make migrate && make seed
```

---

## Branching

| Branch | Purpose |
|--------|---------|
| `main` | Production-ready, protected |
| `develop` | Integration branch |
| `feature/*` | New features — branch from `develop` |
| `fix/*` | Bug fixes |
| `chore/*` | Deps, tooling, no functional change |

---

## Commit Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add QR code generation endpoint
fix: handle expired URL redirect gracefully
chore: bump Laravel to 12.5
test: add coverage for DeviceDetectionService
docs: update README with new env vars
perf: replace serial geo-IP calls with batch resolve
```

---

## Pull Request Checklist

Before opening a PR, ensure all of the following pass locally:

```bash
make test        # Full PHPUnit suite — must be 100% green
make lint        # Pint dry run — zero violations
```

Then in your PR description:

- [ ] Tests added or updated for every changed behaviour
- [ ] `make lint` passes with zero violations
- [ ] `README.md` updated if public API or config changed
- [ ] No new `dd()`, `var_dump()`, `dump()` calls
- [ ] New env vars added to `.env.example` with sensible defaults

---

## Architecture Principles

### No logic in controllers
Controllers resolve dependencies, delegate to services, and return responses. All business logic belongs in `app/Services/`.

### No I/O in the redirect path
`RedirectController` must never make HTTP calls, trigger DB writes, or block on any external service. Analytics are always dispatched as queued jobs.

### Services are injectable
All services accept dependencies via constructor injection. This keeps tests fast — mocking a service should never require Mockery to mock a static facade.

### Cache keys are centralised
All Redis key names live in `UrlCacheService`. No raw `Cache::get('url:...')` calls in controllers or jobs — this makes key refactors a single-file change.

---

## Running Tests in CI Mode

The full CI matrix uses SQLite in-memory + Redis DB 15:

```bash
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=":memory:" \
REDIS_DB=15 \
QUEUE_CONNECTION=sync \
php artisan test --parallel
```

No Docker required for CI — Redis is the only external dependency, and GitHub Actions provides it as a service container.

---

## Adding a New Analytics Dimension

To add a new analytics field (e.g. `language` from the `Accept-Language` header):

1. Add the column to `create_clicks_table` migration
2. Add the field to `Click::$fillable` and `$casts`
3. Populate it in `ProcessClickAnalytics::handle()`
4. Add a `top_languages` aggregation in `AnalyticsService::buildStats()`
5. Add the index in the migration: `$table->index(['url_id', 'language'])`
6. Add a `@OA\Property` entry to `UrlStatsResource`
7. Write a unit test in `ProcessClickAnalyticsTest` for the new field

---

## Questions?

Open a GitHub Discussion or file an issue with the `question` label.
