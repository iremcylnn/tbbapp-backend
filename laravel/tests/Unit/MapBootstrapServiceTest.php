<?php

namespace Tests\Unit;

use App\Map\MapBootstrapService;
use App\Sources\LocationSource;
use PHPUnit\Framework\TestCase;

/**
 * Proves the serving invariants hold for ANY source implementation: the stub
 * below returns deliberately hostile raw rows (disabled, out-of-province,
 * unsorted, with extra fields) and the service must still emit a clean,
 * contract-shaped payload. No framework, no database — pure logic.
 */
class MapBootstrapServiceTest extends TestCase
{
    private function service(): MapBootstrapService
    {
        $stub = new class implements LocationSource
        {
            public function categories(): array
            {
                return [
                    ['id' => 5, 'title' => 'Spor', 'status' => 'active'],
                    ['id' => 2, 'title' => 'Kapalı Kategori', 'status' => 'disabled'],
                    ['id' => 1, 'title' => 'Belediye', 'status' => 'active'],
                ];
            }

            public function places(): array
            {
                return [
                    ['id' => 9, 'title' => 'Sıra Testi', 'province_id' => 59, 'district_id' => 2, 'lat' => 41.1, 'long' => 27.8, 'status' => 'active', 'category_id' => 5],
                    ['id' => 3, 'title' => 'Kapalı Yer', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9, 'long' => 27.5, 'status' => 'disabled', 'category_id' => 1],
                    ['id' => 4, 'title' => 'İstanbul Yeri', 'province_id' => 34, 'district_id' => 1, 'lat' => 41.0, 'long' => 28.9, 'status' => 'active', 'category_id' => 1],
                    ['id' => 1, 'title' => 'Belediye Binası', 'province_id' => 59, 'district_id' => 1, 'lat' => 40.9778, 'long' => 27.5147, 'status' => 'active', 'category_id' => 1],
                ];
            }

            public function version(): int
            {
                return 42;
            }
        };

        return new MapBootstrapService($stub);
    }

    public function test_disabled_rows_never_leave_the_service(): void
    {
        $payload = $this->service()->payload();

        $this->assertSame([1, 5], array_column($payload['categories'], 'id'));
        $this->assertNotContains(3, array_column($payload['places'], 'id'));
    }

    public function test_out_of_province_places_are_excluded(): void
    {
        $payload = $this->service()->payload();

        $this->assertNotContains(4, array_column($payload['places'], 'id'));
    }

    public function test_output_is_sorted_by_id_regardless_of_source_order(): void
    {
        $payload = $this->service()->payload();

        $this->assertSame([1, 9], array_column($payload['places'], 'id'));
        $this->assertSame([1, 5], array_column($payload['categories'], 'id'));
    }

    public function test_rows_are_stripped_to_contract_fields(): void
    {
        $payload = $this->service()->payload();

        $this->assertSame(['id', 'title'], array_keys($payload['categories'][0]));
        $this->assertSame(
            ['id', 'title', 'district_id', 'lat', 'long', 'category_id'],
            array_keys($payload['places'][0])
        );
        // status/province_id are server-side invariants — never serialized.
        $this->assertArrayNotHasKey('status', $payload['places'][0]);
        $this->assertArrayNotHasKey('province_id', $payload['places'][0]);
    }

    public function test_etag_is_a_version_key_from_the_source(): void
    {
        $this->assertSame('"map-v42"', $this->service()->etag());
    }
}
