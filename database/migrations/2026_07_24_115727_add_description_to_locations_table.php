<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The nullable description column BACKEND.md listed as an open item
     * (the app's details sheet displays one) — approved 2026-07-24.
     * Serialized in the bootstrap payload; the contract marks it optional.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
