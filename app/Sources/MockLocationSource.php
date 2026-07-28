<?php

namespace App\Sources;

/**
 * Serves the canonical in-code dataset — the API works with no database at
 * all (MAP_SOURCE=mock). Rows are returned unmodified: raw, unfiltered, in
 * whatever order the dataset lists them; MapBootstrapService applies the
 * rules exactly as it would for any other source.
 */
class MockLocationSource implements LocationSource
{
    public function categories(): array
    {
        return MapSeedData::categories();
    }

    public function districts(): array
    {
        return MapSeedData::districts();
    }

    public function places(): array
    {
        return MapSeedData::places();
    }

    public function version(): int
    {
        // Static data → constant version → the mock ETag never rotates.
        return MapSeedData::VERSION;
    }
}
