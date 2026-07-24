<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Location;
use App\Models\LocationCategory;
use Database\Seeders\MapSeeder;
use Tests\PostgresTestCase;

class MapSeederTest extends PostgresTestCase
{
    public function test_seeds_the_canonical_dataset(): void
    {
        $this->seed(MapSeeder::class);

        $this->assertSame(11, District::count());
        $this->assertSame(10, LocationCategory::count());
        $this->assertSame(23, Location::count());
        $this->assertSame('Tekirdağ Büyükşehir Belediyesi', Location::find(1)->title);
        $this->assertSame('Belediye', LocationCategory::find(1)->title);
        $this->assertSame('Süleymanpaşa', District::find(1)->title);
        $this->assertNotNull(Location::find(1)->description);
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(MapSeeder::class);
        $this->seed(MapSeeder::class);

        $this->assertSame(11, District::count());
        $this->assertSame(10, LocationCategory::count());
        $this->assertSame(23, Location::count());
    }

    public function test_id_less_inserts_work_after_seeding(): void
    {
        $this->seed(MapSeeder::class);

        // The seeder inserted explicit ids and then bumped the sequences —
        // without the setval calls Postgres would try to hand out id 1 here
        // and collide.
        $category = LocationCategory::create(['title' => 'Yeni Kategori']);
        $this->assertSame(11, $category->id);

        $place = Location::create([
            'title' => 'Yeni Yer',
            'district_id' => 1,
            'lat' => 40.98,
            'long' => 27.51,
            'category_id' => $category->id,
        ]);
        $this->assertSame(24, $place->id);
    }
}
