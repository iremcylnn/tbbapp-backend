<?php

namespace App\Osm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The single owner of the Overpass API origin (ratified: routes/services
 * never call external origins directly — source classes own them).
 *
 * Overpass is a free, community-run query API over OpenStreetMap data.
 * Public instances get busy, so we try mirrors in order and use POST
 * (queries with Turkish characters don't survive naive URL-encoding).
 */
class OverpassClient
{
    private const MIRRORS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];

    /**
     * Fetch all pharmacies inside a Turkish district (ilçe, admin_level=6).
     *
     * Returns raw-ish rows: [{osm_id, title, lat, long}], unnamed elements
     * included (title null) — the importer decides what to do with them.
     *
     * @return list<array{osm_id: string, title: ?string, lat: float, long: float}>
     */
    public function pharmaciesInDistrict(string $districtName): array
    {
        // nwr = nodes + ways + relations; "out center" adds a computed
        // center point for ways/relations (a pharmacy drawn as a building
        // outline has no lat/lon of its own).
        $query = <<<QL
            [out:json][timeout:60];
            area["name"="{$districtName}"]["boundary"="administrative"]["admin_level"="6"]->.a;
            nwr["amenity"="pharmacy"](area.a);
            out center;
            QL;

        $elements = $this->run($query)['elements'] ?? [];

        return array_values(array_map(fn (array $el) => [
            'osm_id' => $el['type'].'/'.$el['id'],
            'title' => isset($el['tags']['name']) ? trim($el['tags']['name']) : null,
            'lat' => (float) ($el['lat'] ?? $el['center']['lat']),
            'long' => (float) ($el['lon'] ?? $el['center']['lon']),
        ], $elements));
    }

    /** @return array<string, mixed> decoded Overpass JSON */
    private function run(string $query): array
    {
        $lastError = 'no mirror attempted';

        foreach (self::MIRRORS as $mirror) {
            try {
                $response = Http::asForm()->timeout(90)->post($mirror, ['data' => $query]);
            } catch (ConnectionException $e) {
                $lastError = "{$mirror}: {$e->getMessage()}";
                continue;
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $lastError = "{$mirror}: HTTP {$response->status()}";
        }

        throw new RuntimeException("Overpass sorgusu başarısız oldu — {$lastError}");
    }
}
