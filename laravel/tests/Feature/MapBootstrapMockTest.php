<?php

namespace Tests\Feature;

use App\Sources\LocationSource;
use App\Sources\MockLocationSource;
use Tests\TestCase;

/**
 * The endpoint over the MOCK source — deliberately no database involved,
 * proving the ratified mock-first promise: the API is fully functional
 * before (or without) PostgreSQL.
 *
 * Singleton caveat: map.source must be set BEFORE anything resolves
 * LocationSource — the container caches the first resolution.
 */
class MapBootstrapMockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['map.source' => 'mock']);
    }

    public function test_serves_the_full_dataset_with_an_etag(): void
    {
        $response = $this->getJson('/api/map/bootstrap');

        $response->assertOk()
            ->assertHeader('ETag', '"map-v3"')
            ->assertJsonCount(11, 'categories')
            ->assertJsonCount(11, 'districts')
            ->assertJsonCount(23, 'places');

        $this->assertInstanceOf(MockLocationSource::class, app(LocationSource::class));
    }

    public function test_payload_matches_the_contract_shape(): void
    {
        $response = $this->getJson('/api/map/bootstrap');

        $response->assertJsonStructure([
            'categories' => [['id', 'title']],
            'districts' => [['id', 'title']],
            'places' => [['id', 'title', 'district_id', 'lat', 'long', 'category_id', 'description']],
        ]);

        // Numbers, not strings — the raw JSON must contain unquoted floats.
        $this->assertStringContainsString('"lat":40.9778', $response->getContent());
        $this->assertStringContainsString('"long":27.5147', $response->getContent());
    }

    public function test_matching_if_none_match_returns_304_with_empty_body(): void
    {
        $etag = $this->getJson('/api/map/bootstrap')->headers->get('ETag');

        $response = $this->getJson('/api/map/bootstrap', ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
    }

    public function test_wildcard_if_none_match_returns_304(): void
    {
        $this->getJson('/api/map/bootstrap', ['If-None-Match' => '*'])
            ->assertStatus(304);
    }

    public function test_stale_etag_returns_fresh_200(): void
    {
        $this->getJson('/api/map/bootstrap', ['If-None-Match' => '"map-v0"'])
            ->assertOk()
            ->assertHeader('ETag', '"map-v3"');
    }

    public function test_unknown_api_route_is_json_404(): void
    {
        $this->get('/api/nonexistent')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
