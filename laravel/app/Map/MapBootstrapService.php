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
     * The active categories, {id, title}, sorted by id.
     *
     * Public because the admin panel needs the same list for its dropdowns:
     * every read of map data goes through the configured source, so the panel
     * can't drift from what the API serves (nor break under MAP_SOURCE=mock).
     *
     * @return list<array{id: int, title: string}>
     */
    public function categories(): array
    {
        return $this->idTitleRows($this->source->categories());
    }

    /**
     * The active districts, {id, title}, sorted by id. Same reasoning as
     * categories().
     *
     * @return list<array{id: int, title: string}>
     */
    public function districts(): array
    {
        return $this->idTitleRows($this->source->districts());
    }

    /**
     * The bootstrap payload, contract-shaped:
     * {
     *   categories: [{id, title}],
     *   districts:  [{id, title}],
     *   places:     [{id, title, district_id, lat, long, category_id, description}]
     * }
     */
    public function payload(): array
    {
        // status/province_id are read WITHOUT a ?? fallback on purpose, unlike
        // the optional description below: they are the filter keys the whole
        // soft-delete contract rests on. A source that omits one must blow up
        // loudly here, not silently publish rows nobody vetted.
        $places = array_values(array_filter(
            $this->source->places(),
            fn (array $p) => $p['status'] === 'active' && (int) $p['province_id'] === 59,
        ));
        usort($places, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return [
            'categories' => $this->categories(),
            'districts' => $this->districts(),
            'places' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'title' => $p['title'],
                'district_id' => $p['district_id'],
                'lat' => $p['lat'],
                'long' => $p['long'],
                'category_id' => $p['category_id'],
                'description' => $p['description'] ?? null,
            ], $places),
        ];
    }

    /**
     * Shared shape for the two lookup tables (categories, districts):
     * active rows only, sorted by id, stripped to {id, title}.
     *
     * @param  list<array{id: int, title: string, status: string}>  $rows
     * @return list<array{id: int, title: string}>
     */
    private function idTitleRows(array $rows): array
    {
        $active = array_values(array_filter(
            $rows,
            fn (array $r) => $r['status'] === 'active',
        ));
        usort($active, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return array_map(fn (array $r) => [
            'id' => $r['id'],
            'title' => $r['title'],
        ], $active);
    }
}
