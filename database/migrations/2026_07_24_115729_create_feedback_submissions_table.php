<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Citizen complaint/request submissions (spec contract: {kind, description,
     * lat?, long?, location_id?}). Column naming follows the guide's convention
     * (lat/long, location_id) — NOT the old server's latitude/longitude/placeId;
     * the spec contract wins that collision.
     */
    public function up(): void
    {
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('kind')->index();
            $table->text('description');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
    }
};
