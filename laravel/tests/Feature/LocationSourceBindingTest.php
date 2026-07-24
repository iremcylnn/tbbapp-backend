<?php

namespace Tests\Feature;

use App\Sources\DatabaseLocationSource;
use App\Sources\LocationSource;
use App\Sources\MockLocationSource;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The config-driven source swap (AppServiceProvider). Resolving
 * DatabaseLocationSource does not touch the database — construction is
 * lazy — so these tests run without PostgreSQL.
 */
class LocationSourceBindingTest extends TestCase
{
    public function test_database_config_resolves_the_database_source(): void
    {
        config(['map.source' => 'database']);

        $this->assertInstanceOf(DatabaseLocationSource::class, app(LocationSource::class));
    }

    public function test_mock_config_resolves_the_mock_source(): void
    {
        config(['map.source' => 'mock']);

        $this->assertInstanceOf(MockLocationSource::class, app(LocationSource::class));
    }

    public function test_unknown_source_fails_loudly(): void
    {
        config(['map.source' => 'nonsense']);

        $this->expectException(InvalidArgumentException::class);

        app(LocationSource::class);
    }

    /**
     * The singleton caveat, demonstrated: after the first resolution the
     * instance is cached — a later config change alone does nothing;
     * forgetInstance() is required for a mid-process swap.
     */
    public function test_singleton_caches_first_resolution(): void
    {
        config(['map.source' => 'database']);
        $first = app(LocationSource::class);

        config(['map.source' => 'mock']);
        $this->assertInstanceOf(DatabaseLocationSource::class, app(LocationSource::class));
        $this->assertSame($first, app(LocationSource::class));

        app()->forgetInstance(LocationSource::class);
        $this->assertInstanceOf(MockLocationSource::class, app(LocationSource::class));
    }
}
