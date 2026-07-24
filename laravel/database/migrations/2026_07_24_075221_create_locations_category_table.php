<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guide's spec, verbatim: id, title, status — and NO timestamps.
     * `status` is the soft-delete mechanism (rows are deactivated, never
     * DELETEd, because the app cross-references categories by permanent id).
     */
    public function up(): void
    {
        Schema::create('locations_category', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_category');
    }
};
