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
        // two separate counters — failed logins must not eat the feedback
        // quota (and vice versa); they're unrelated threat models.
        $tooMany = fn () => response()->json(
            ['message' => 'Çok fazla deneme yapıldı, lütfen daha sonra tekrar deneyin.'],
            429
        );

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by($request->ip())
            ->response($tooMany));

        RateLimiter::for('public-write', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by($request->user()?->id ?? $request->ip())
            ->response($tooMany));
    }
}
