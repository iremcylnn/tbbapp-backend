<?php

namespace Tests\Unit;

use App\Sources\MapSeedData;
use Tests\TestCase;

/**
 * Everywhere else in this system, map freshness is mechanical: Postgres
 * statement triggers bump map_version on any write, from any origin, with no
 * way to forget. The mock source is the one exception — its version is a
 * hand-written constant, so under MAP_SOURCE=mock an edited dataset served
 * under an unchanged ETag would leave clients caching stale data forever
 * (a 304 tells them not to even ask again).
 *
 * This test is the missing enforcement. Edit the dataset and it fails,
 * telling you to bump MapSeedData::VERSION and paste the new fingerprint
 * below. That is deliberately a small chore: it is the human step that
 * replaces the trigger the mock source doesn't have.
 */
class MapSeedDataVersionTest extends TestCase
{
    /**
     * Bump BOTH of these together, never one alone.
     * v3 → this fingerprint (2026-07-27).
     */
    private const EXPECTED_VERSION = 3;

    private const EXPECTED_FINGERPRINT = 'd2a232b8734259be7800720900da08ddde34ca3a2ac706a94998264db0731ee2';

    public function test_dataset_edits_require_a_version_bump(): void
    {
        $fingerprint = self::fingerprint();

        $this->assertSame(
            self::EXPECTED_FINGERPRINT,
            $fingerprint,
            "The mock dataset changed but its fingerprint didn't.\n".
            "Nothing bumps the mock ETag automatically, so clients would keep a stale cache.\n".
            'Fix: increment MapSeedData::VERSION, then set EXPECTED_VERSION and '.
            "EXPECTED_FINGERPRINT in this test to:\n  version: ".(MapSeedData::VERSION + 1).
            "\n  fingerprint: {$fingerprint}",
        );

        $this->assertSame(
            self::EXPECTED_VERSION,
            MapSeedData::VERSION,
            'MapSeedData::VERSION moved without the dataset changing, or this test was '.
            'not updated alongside it — the two must always travel together.',
        );
    }

    /**
     * Coordinates are normalised to fixed 7-decimal strings before hashing,
     * matching the decimal(10,7) columns. Hashing the raw floats would make
     * the fingerprint depend on PHP's serialize_precision ini setting, so the
     * test could fail on a differently configured machine without anyone
     * having touched the data.
     */
    private static function fingerprint(): string
    {
        $places = array_map(function (array $place): array {
            $place['lat'] = number_format($place['lat'], 7, '.', '');
            $place['long'] = number_format($place['long'], 7, '.', '');

            return $place;
        }, MapSeedData::places());

        return hash('sha256', serialize([
            MapSeedData::districts(),
            MapSeedData::categories(),
            $places,
        ]));
    }
}
