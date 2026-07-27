<?php

namespace App\Providers;

use App\Sources\DatabaseLocationSource;
use App\Sources\LocationSource;
use App\Sources\MockLocationSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The source-swap seam: which LocationSource the app runs on is a
        // config decision (MAP_SOURCE in .env), never hardcoded at use sites.
        //
        // singleton(): the closure runs once per request lifecycle and the
        // instance is reused — conventional for stateless services. Test
        // caveat: because the first resolution is cached, tests must set
        // config(['map.source' => ...]) BEFORE anything resolves the
        // interface (or call app()->forgetInstance(LocationSource::class)).
        $this->app->singleton(LocationSource::class, function ($app) {
            $source = $app['config']->get('map.source');

            return match ($source) {
                'database' => new DatabaseLocationSource(),
                'mock' => new MockLocationSource(),
                default => throw new InvalidArgumentException(
                    "Unknown map source [{$source}]; expected 'database' or 'mock' (MAP_SOURCE)."
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mirrors the old server: 20 requests / 15 minutes, and DELIBERATELY
        // separate counters — one threat model must not eat another's quota.
        // Laravel keys a named limiter as md5($limiterName.$limit->key) with
        // NO route component, so every route sharing a name shares one bucket:
        // the names below are the actual isolation boundary.
        $message = 'Çok fazla deneme yapıldı, lütfen daha sonra tekrar deneyin.';

        // The Blade admin panel posts a form and expects a form response; the
        // mobile app and curl expect JSON. Same limit, right medium for each.
        $tooMany = fn (Request $request) => $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => $message], 429)
            : back()->withErrors(['key' => $message]);

        // Citizen auth (register/login/password reset), keyed by IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by($request->ip())
            ->response($tooMany));

        // Admin panel login. A SEPARATE counter from citizen auth even though
        // both are logins: a locked-out admin retrying from the municipality's
        // shared NAT must not consume the citizen login quota for every user
        // behind that same address.
        RateLimiter::for('admin-auth', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by($request->ip())
            ->response($tooMany));

        // Citizen submissions, keyed by user (falling back to IP when
        // unauthenticated) — failed logins must not eat the feedback quota.
        RateLimiter::for('public-write', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by($request->user()?->id ?? $request->ip())
            ->response($tooMany));
    }
}
