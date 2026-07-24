<?php

namespace App\Providers;

use App\Sources\DatabaseLocationSource;
use App\Sources\LocationSource;
use App\Sources\MockLocationSource;
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
        //
    }
}
