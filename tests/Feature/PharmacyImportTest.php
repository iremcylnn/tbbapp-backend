<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationCategory;
use App\Osm\OverpassClient;
use App\Osm\PharmacyImporter;
use Illuminate\Support\Facades\Http;
use Tests\PostgresTestCase;

class PharmacyImportTest extends PostgresTestCase
{
    /** A fake Overpass response: one node, one way (center coords), one unnamed. */
    private function fakeOverpass(): void
    {
        Http::fake(['*' => Http::response([
            'elements' => [
                ['type' => 'node', 'id' => 101, 'lat' => 40.98, 'lon' => 27.51,
                    'tags' => ['amenity' => 'pharmacy', 'name' => 'Merkez Eczanesi']],
                ['type' => 'way', 'id' => 202, 'center' => ['lat' => 40.97, 'lon' => 27.52],
                    'tags' => ['amenity' => 'pharmacy', 'name' => 'Sahil Eczanesi']],
                ['type' => 'node', 'id' => 303, 'lat' => 40.99, 'lon' => 27.50,
                    'tags' => ['amenity' => 'pharmacy']], // no name → skipped
            ],
        ])]);
    }

    private function runImport(bool $dryRun = false): array
    {
        $category = LocationCategory::query()->firstOrCreate(['title' => 'Eczane'], ['status' => 'active']);
        $importer = new PharmacyImporter(new OverpassClient);

        return $importer->import('Süleymanpaşa', 1, $category->id, $dryRun);
    }

    public function test_import_creates_named_pharmacies_and_skips_unnamed(): void
    {
        $this->fakeOverpass();

        $result = $this->runImport();

        $this->assertSame(['Merkez Eczanesi', 'Sahil Eczanesi'], $result['created']);
        $this->assertSame(1, $result['skipped']);

        $merkez = Location::query()->where('osm_id', 'node/101')->firstOrFail();
        $this->assertSame('Merkez Eczanesi', $merkez->title);
        $this->assertSame(1, $merkez->district_id);
        $this->assertSame('active', $merkez->status);
        $this->assertSame(59, $merkez->province_id);

        // The way element got its coordinates from the computed center.
        $sahil = Location::query()->where('osm_id', 'way/202')->firstOrFail();
        $this->assertSame(40.97, $sahil->lat);
    }

    public function test_reimport_updates_in_place_and_keeps_admin_disabling(): void
    {
        $this->fakeOverpass();

        $this->runImport();
        $merkez = Location::query()->where('osm_id', 'node/101')->firstOrFail();
        $merkez->update(['status' => 'disabled']); // admin hid a bad row

        $result = $this->runImport();

        $this->assertSame([], $result['created']);
        $this->assertCount(2, $result['updated']);
        $this->assertDatabaseCount('locations', 2); // no duplicates
        $this->assertSame($merkez->id, Location::query()->where('osm_id', 'node/101')->value('id'));
        $this->assertSame('disabled', $merkez->refresh()->status); // re-import didn't re-enable
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeOverpass();

        $result = $this->runImport(dryRun: true);

        $this->assertCount(2, $result['created']);
        $this->assertDatabaseCount('locations', 0);
    }
}
