<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Location;
use App\Models\LocationCategory;
use App\Sources\MapSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MapSeeder extends Seeder
{
    /**
     * Idempotent by design: upsert on id, so re-running never duplicates rows.
     * No manual version bump anywhere — the Postgres statement-level triggers
     * fire on these upsert statements like on any other write.
     */
    public function run(): void
    {
        // Districts also ship inside their migration (the locations FK needs
        // them); upserting here keeps the seeder the complete, idempotent
        // owner of the canonical dataset regardless.
        District::upsert(
            MapSeedData::districts(),
            uniqueBy: ['id'],
            update: ['title', 'status'],
        );

        LocationCategory::upsert(
            MapSeedData::categories(),
            uniqueBy: ['id'],
            update: ['title', 'status'],
        );

        Location::upsert(
            MapSeedData::places(),
            uniqueBy: ['id'],
            update: ['title', 'province_id', 'district_id', 'lat', 'long', 'status', 'category_id', 'description'],
        );

        // Explicit ids were inserted above; move each table's auto-increment
        // sequence past MAX(id) so a future id-less INSERT doesn't collide.
        DB::statement("SELECT setval(pg_get_serial_sequence('districts','id'), (SELECT MAX(id) FROM districts))");
        DB::statement("SELECT setval(pg_get_serial_sequence('locations_category','id'), (SELECT MAX(id) FROM locations_category))");
        DB::statement("SELECT setval(pg_get_serial_sequence('locations','id'), (SELECT MAX(id) FROM locations))");
    }
}
