<?php

namespace App\Providers;

use App\Models\Url;
use App\Observers\UrlObserver;
use App\Policies\UrlPolicy;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DeviceDetectionService;
use App\Services\Analytics\GeoIpService;
use App\Services\Cache\UrlCacheService;
use App\Services\Shortener\UrlShortenerService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Url::class => UrlPolicy::class,
    ];

    public function register(): void
    {
        // Bind GeoIpService with pre-configured HTTP client
        $this->app->singleton(GeoIpService::class, function () {
            return new GeoIpService(Http::getFacadeRoot());
        });

        // Singleton services (stateless, safe to share)
        $this->app->singleton(UrlCacheService::class);
        $this->app->singleton(DeviceDetectionService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(UrlShortenerService::class);
    }

    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Model observers
        Url::observe(UrlObserver::class);

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Shorten endpoint: stricter — prevents URL spam
        RateLimiter::for('shorten', function (Request $request) {
            $limit = config('url-shortener.rate_limits.shorten', 10);

            return $request->user()
                ? Limit::perMinute($limit)->by($request->user()->id)
                : Limit::perMinute($limit)->by($request->ip());
        });

        // Redirect endpoint: lenient — bots scanning short codes shouldn't block users
        RateLimiter::for('redirect', function (Request $request) {
            $limit = config('url-shortener.rate_limits.redirect', 120);

            return Limit::perMinute($limit)->by($request->ip());
        });

        // Geo-IP queue throttle: stay within free-tier limits
        RateLimiter::for('geo-ip-lookup', function (Request $request) {
            return Limit::perSecond(1);
        });
    }
}
