<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\MapVersion;
use Database\Seeders\MapSeeder;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

/**
 * The Postgres statement-level triggers — the reason freshness tracking
 * can't be bypassed by ANY write path. These tests exercise write origins
 * that Eloquent observers would have missed (bulk queries, raw SQL), which
 * is exactly why the triggers exist.
 */
class MapVersionTest extends PostgresTestCase
{
    public function test_migration_created_the_version_row(): void
    {
        $this->assertGreaterThanOrEqual(1, MapVersion::current());
        $this->assertSame(1, DB::table('map_version')->count());
    }

    public function test_eloquent_writes_bump_on_both_tables(): void
    {
        $before = MapVersion::current();

        $category = LocationCategory::factory()->create();
        $this->assertSame($before + 1, MapVersion::current());

        $place = Location::factory()->for($category, 'category')->create();
        $this->assertSame($before + 2, MapVersion::current());

        $place->update(['title' => 'Yeni Başlık']);
        $this->assertSame($before + 3, MapVersion::current());

        $place->delete();
        $this->assertSame($before + 4, MapVersion::current());
    }

    public function test_bulk_update_bumps_exactly_once(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->count(5)->create();

        $before = MapVersion::current();

        // One statement, five rows — FOR EACH STATEMENT means ONE bump.
        Location::query()->update(['status' => 'disabled']);

        $this->assertSame($before + 1, MapVersion::current());
    }

    public function test_raw_sql_bumps_too(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->create();

        $before = MapVersion::current();

        // Deliberately bypasses Eloquent entirely — an observer would never
        // see this write; the trigger does.
        DB::statement("UPDATE locations SET title = 'Ham SQL' WHERE province_id = 59");

        $this->assertSame($before + 1, MapVersion::current());
    }

    public function test_truncate_bumps(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->create();

        $before = MapVersion::current();

        // TRUNCATE fires no row-level triggers at all; only a statement-level
        // TRUNCATE trigger catches it. CASCADE because feedback_submissions
        // references locations — Postgres refuses a plain TRUNCATE on a
        // referenced table.
        DB::statement('TRUNCATE locations CASCADE');

        $this->assertSame($before + 1, MapVersion::current());
    }

    public function test_district_writes_bump(): void
    {
        $before = MapVersion::current();

        \App\Models\District::query()->whereKey(1)->update(['title' => 'Yeni Ad']);

        $this->assertSame($before + 1, MapVersion::current());
    }

    public function test_seeding_bumps(): void
    {
        $before = MapVersion::current();

        $this->seed(MapSeeder::class);

        // Eloquent upsert() fires NO model events — with observers the seeder
        // would have needed a manual bump; the triggers make it automatic.
        $this->assertGreaterThan($before, MapVersion::current());
    }
}
