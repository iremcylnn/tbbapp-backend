<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity key for rows imported from OpenStreetMap ("node/123", "way/456").
 * The importer upserts on this column, so re-running an import updates the
 * existing rows in place instead of duplicating them — and the app-facing
 * `id` stays permanent (ratified: IDs are never re-keyed). NULL for rows
 * that didn't come from OSM (seed data, admin-approved citizen proposals).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('osm_id', 32)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('osm_id');
        });
    }
};
