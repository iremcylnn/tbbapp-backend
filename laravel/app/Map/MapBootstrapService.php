<?php

namespace App\Map;

use App\Sources\LocationSource;

/**
 * The single owner of the map serving rules. Sources hand over RAW rows;
 * every invariant the API promises is enforced here, in exactly one place,
 * for ANY source implementation:
 *
 *   - only status = 'active' rows leave the server (soft-delete contract);
 *   - only province_id = 59 (Tekirdağ) places leave the server;
 *   - output is sorted by id, so identical data always serializes
 *     identically (an ETag must never vary for unchanged content);
 *   - rows are stripped to contract fields — status/province_id are
 *     server-side concerns and are never serialized.
 */
class MapBootstrapService
{
    public function __construct(
        private readonly LocationSource $source,
    ) {
    }

    /**
     * Version-key ETag: "map-v{N}" from the source's freshness marker.
     * Deliberately NOT a content hash — answering If-None-Match must not
     * require querying and serializing the whole payload.
     */
    public function etag(): string
    {
        return '"map-v'.$this->source->version().'"';
    }

    /**
     * The bootstrap payload, contract-shaped:
     * {categories: [{id, title}], places: [{id, title, district_id, lat, long, category_id}]}
     */
    public function payload(): array
    {
        $categories = array_values(array_filter(
            $this->source->categories(),
            fn (array $c) => $c['status'] === 'active',
        ));
        usort($categories, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        $places = array_values(array_filter(
            $this->source->places(),
            fn (array $p) => $p['status'] === 'active' && (int) $p['province_id'] === 59,
        ));
        usort($places, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return [
            'categories' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'title' => $c['title'],
            ], $categories),
            'places' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'title' => $p['title'],
                'district_id' => $p['district_id'],
                'lat' => $p['lat'],
                'long' => $p['long'],
                'category_id' => $p['category_id'],
            ], $places),
        ];
    }
}
