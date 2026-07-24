<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Location;
use App\Models\LocationCategory;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

/**
 * The endpoint over the DATABASE source, against real PostgreSQL 17 —
 * the float-serialization gotcha and the trigger-driven ETag rotation
 * only exist there.
 */
class MapBootstrapDatabaseTest extends PostgresTestCase
{
    public function test_serves_only_active_tekirdag_rows(): void
    {
        $category = LocationCategory::factory()->create();
        LocationCategory::factory()->disabled()->create();

        $visible = Location::factory()->for($category, 'category')->create();
        Location::factory()->for($category, 'category')->disabled()->create();
        Location::factory()->for($category, 'category')->create(['province_id' => 34]);

        // The 11 real districts ship in the migration; a disabled extra one
        // must not appear alongside them.
        District::factory()->disabled()->create();

        $response = $this->getJson('/api/map/bootstrap');

        $response->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonCount(11, 'districts')
            ->assertJsonCount(1, 'places')
            ->assertJsonPath('places.0.id', $visible->id);
    }

    public function test_description_serializes_and_nulls_are_allowed(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->create([
            'description' => 'Ana hizmet binası',
        ]);
        Location::factory()->for($category, 'category')->create([
            'description' => null,
        ]);

        $response = $this->getJson('/api/map/bootstrap');

        $response->assertJsonPath('places.0.description', 'Ana hizmet binası')
            ->assertJsonPath('places.1.description', null);
    }

    public function test_etag_rotates_when_a_district_title_changes(): void
    {
        // districts has NO timestamps — only its Postgres trigger makes this
        // edit visible to freshness tracking (same design as categories).
        $etag = $this->getJson('/api/map/bootstrap')->headers->get('ETag');

        District::query()->whereKey(1)->update(['title' => 'Yeni İlçe Adı']);

        $this->getJson('/api/map/bootstrap', ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJsonPath('districts.0.title', 'Yeni İlçe Adı');
    }

    public function test_postgres_decimals_serialize_as_json_numbers(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->create([
            'lat' => 40.9778,
            'long' => 27.5147,
        ]);

        $response = $this->getJson('/api/map/bootstrap');

        // Postgres hands decimals to PHP as strings; the model's float casts
        // must turn them back into numbers before serialization.
        $this->assertStringContainsString('"lat":40.9778', $response->getContent());
        $this->assertStringContainsString('"long":27.5147', $response->getContent());
        $this->assertSame(40.9778, $response->json('places.0.lat'));
    }

    public function test_etag_rotates_when_a_category_title_changes(): void
    {
        // locations_category has NO timestamps — only the Postgres trigger
        // can make this edit visible to freshness tracking.
        $category = LocationCategory::factory()->create(['title' => 'Eski Ad']);

        $etag = $this->getJson('/api/map/bootstrap')->headers->get('ETag');

        LocationCategory::query()->whereKey($category->id)->update(['title' => 'Yeni Ad']);

        $this->getJson('/api/map/bootstrap', ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJsonPath('categories.0.title', 'Yeni Ad');
    }

    public function test_etag_rotates_when_a_place_is_disabled(): void
    {
        $category = LocationCategory::factory()->create();
        $place = Location::factory()->for($category, 'category')->create();

        $etag = $this->getJson('/api/map/bootstrap')->headers->get('ETag');

        $place->update(['status' => 'disabled']);

        $response = $this->getJson('/api/map/bootstrap', ['If-None-Match' => $etag]);

        $response->assertOk()->assertJsonCount(0, 'places');
        $this->assertNotSame($etag, $response->headers->get('ETag'));
    }

    public function test_the_304_path_touches_only_the_version_table(): void
    {
        $category = LocationCategory::factory()->create();
        Location::factory()->for($category, 'category')->count(3)->create();

        $etag = $this->getJson('/api/map/bootstrap')->headers->get('ETag');

        DB::enableQueryLog();
        $this->getJson('/api/map/bootstrap', ['If-None-Match' => $etag])->assertStatus(304);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The whole point of the version-key design: a 304 costs one
        // single-row SELECT — no locations/categories query, no serialization.
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('map_version', $queries[0]['query']);
    }
}
