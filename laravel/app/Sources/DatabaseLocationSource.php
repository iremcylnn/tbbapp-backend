<?php

namespace App\Sources;

use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\MapVersion;

/**
 * Reads the seeded PostgreSQL tables. No WHERE clauses on purpose — raw rows
 * out, business rules live in MapBootstrapService (see LocationSource).
 * The models' float casts guarantee lat/long leave here as numbers, not the
 * strings Postgres hands PHP for decimal columns.
 */
class DatabaseLocationSource implements LocationSource
{
    public function categories(): array
    {
        return LocationCategory::query()
            ->get(['id', 'title', 'status'])
            ->map(fn (LocationCategory $c) => $c->toArray())
            ->all();
    }

    public function places(): array
    {
        return Location::query()
            ->get(['id', 'title', 'province_id', 'district_id', 'lat', 'long', 'status', 'category_id'])
            ->map(fn (Location $l) => $l->toArray())
            ->all();
    }

    public function version(): int
    {
        return MapVersion::current();
    }
}
