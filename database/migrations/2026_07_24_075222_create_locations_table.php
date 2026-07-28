<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guide's spec: id, title, province_id (always 59 = Tekirdağ),
     * district_id, lat, long, status, category_id FK, timestamps.
     *
     * - `district_id` is a plain integer for now: a districts table awaits the
     *   guide's decision, so there is nothing to reference yet. Bigint so a
     *   real FK can be added later without a column type change.
     * - `lat`/`long` are decimal(10,7): ~1 cm precision, exact storage. The
     *   JSON contract's number type is produced by an Eloquent cast, not by
     *   the column type (Postgres decimals reach PHP as strings).
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('province_id')->default(59);
            $table->unsignedBigInteger('district_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('long', 10, 7);
            $table->string('status')->default('active');
            $table->foreignId('category_id')->constrained('locations_category');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
