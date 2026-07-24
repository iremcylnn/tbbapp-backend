<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Infrastructure table, not domain data: a single row (id = 1) holding a
     * monotonically increasing counter that changes whenever map data changes.
     * The API turns it into the ETag ("map-v{version}"), so a client's
     * If-None-Match can be answered with 304 at the cost of one single-row
     * SELECT — no payload query, no serialization, no hashing.
     *
     * THE COUNTER IS BUMPED BY POSTGRES TRIGGERS, NOT BY PHP. Nothing in the
     * Laravel codebase writes this row; if you're wondering why the version
     * moves "by itself", it's the statement-level triggers below. They live in
     * the database so that NO write path can bypass freshness tracking:
     * Eloquent saves, bulk upserts (which fire no model events), the seeder,
     * raw SQL, even manual edits in pgAdmin. Statement-level means a bulk
     * UPDATE touching 1,000 rows bumps exactly once, and the bump commits or
     * rolls back atomically with the write that caused it. This also covers
     * locations_category, which has no timestamps to watch.
     */
    public function up(): void
    {
        Schema::create('map_version', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
        });
        DB::table('map_version')->insert(['id' => 1, 'version' => 1]);

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION bump_map_version() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE map_version SET version = version + 1 WHERE id = 1;
                RETURN NULL;  -- ignored for AFTER ... FOR EACH STATEMENT triggers
            END;
            $$;

            CREATE TRIGGER locations_bump_map_version
                AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON locations
                FOR EACH STATEMENT EXECUTE FUNCTION bump_map_version();

            CREATE TRIGGER locations_category_bump_map_version
                AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON locations_category
                FOR EACH STATEMENT EXECUTE FUNCTION bump_map_version();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS locations_bump_map_version ON locations');
        DB::unprepared('DROP TRIGGER IF EXISTS locations_category_bump_map_version ON locations_category');
        DB::unprepared('DROP FUNCTION IF EXISTS bump_map_version()');
        Schema::dropIfExists('map_version');
    }
};
