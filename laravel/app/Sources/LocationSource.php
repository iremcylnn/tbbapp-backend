<?php

namespace App\Sources;

/**
 * The ratified mock-first, source-swappable seam: every map read goes through
 * an implementation of this interface, selected by config('map.source') in
 * AppServiceProvider — mock array today, seeded database tomorrow, scraped
 * page or credentialed feed later, with zero contract change.
 *
 * Sources are DUMB READERS. They return RAW origin rows — status and
 * province_id included — because a source owns its origin, never the business
 * rules. Filtering (status = active, province_id = 59), sorting, and shaping
 * to contract fields all happen in exactly one place: MapBootstrapService.
 * A source that pre-filtered would just be redundant; one that forgot to
 * filter can't leak anything, because the service filters regardless.
 */
interface LocationSource
{
    /**
     * All category rows, raw: {id, title, status}.
     *
     * @return list<array{id: int, title: string, status: string}>
     */
    public function categories(): array;

    /**
     * All place rows, raw:
     * {id, title, province_id, district_id, lat, long, status, category_id}.
     *
     * @return list<array<string, mixed>>
     */
    public function places(): array;

    /**
     * Freshness marker of this origin — the ETag is derived from it, so it
     * must change whenever categories()/places() output would change.
     *
     * This is also the caching slot-in point: when Redis arrives, a cached
     * version key replaces the DB read here and nothing else changes.
     */
    public function version(): int;
}
