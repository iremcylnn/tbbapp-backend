<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-side accessor for the single-row map_version infrastructure table.
 *
 * Nothing in the application WRITES this row — the counter is bumped by
 * Postgres statement-level triggers on locations/locations_category (see the
 * create_map_version_table migration). This model exists so the API can read
 * the current version cheaply to build the ETag.
 */
class MapVersion extends Model
{
    protected $table = 'map_version';

    public $timestamps = false;

    protected $fillable = ['version'];

    /** The current map data version (the ETag source). One single-row SELECT. */
    public static function current(): int
    {
        return (int) static::query()->whereKey(1)->value('version');
    }

    /**
     * Manual/administrative bump only — nothing in the app calls this; the DB
     * triggers own bumping. Kept for emergencies (e.g. forcing clients to
     * refetch without touching data).
     */
    public static function bump(): void
    {
        static::query()->whereKey(1)->increment('version');
    }
}
