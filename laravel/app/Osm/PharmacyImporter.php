<?php

namespace App\Osm;

use App\Models\Location;

/**
 * Writes OSM pharmacies into the locations table.
 *
 * Idempotent by design: rows are matched on osm_id, so a re-run updates
 * name/coordinate changes in place — it never duplicates, and the
 * app-facing location id never changes (ratified: IDs are permanent).
 * The Postgres trigger on locations bumps map_version on any write, so a
 * successful import rotates the bootstrap ETag automatically.
 */
class PharmacyImporter
{
    public function __construct(private OverpassClient $overpass)
    {
    }

    /**
     * @return array{created: list<string>, updated: list<string>, skipped: int}
     */
    public function import(string $districtName, int $districtId, int $categoryId, bool $dryRun): array
    {
        $result = ['created' => [], 'updated' => [], 'skipped' => 0];

        foreach ($this->overpass->pharmaciesInDistrict($districtName) as $row) {
            // An unnamed map element is unusable in the app's list/detail UI.
            if ($row['title'] === null || $row['title'] === '') {
                $result['skipped']++;
                continue;
            }

            $existing = Location::query()->where('osm_id', $row['osm_id'])->first();
            $bucket = $existing === null ? 'created' : 'updated';
            $result[$bucket][] = $row['title'];

            if ($dryRun) {
                continue;
            }

            Location::query()->updateOrCreate(
                ['osm_id' => $row['osm_id']],
                [
                    'title' => $row['title'],
                    'district_id' => $districtId,
                    'category_id' => $categoryId,
                    'lat' => $row['lat'],
                    'long' => $row['long'],
                    // province_id 59 / status 'active' come from DB defaults on
                    // create; deliberately NOT overwritten on update, so an
                    // admin who disabled a bad row keeps it disabled.
                ],
            );
        }

        return $result;
    }
}
