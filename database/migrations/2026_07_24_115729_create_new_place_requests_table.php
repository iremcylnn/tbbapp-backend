<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Citizen "add a new place" suggestions (spec contract: {title,
     * category_id?, description, lat, long}); `title` per the guide's naming —
     * the old server's `name` loses that collision. Approval (admin) turns a
     * pending row into a real locations row; `status` is the pipeline state:
     * pending → approved | rejected, one-way, enforced atomically in code.
     */
    public function up(): void
    {
        Schema::create('new_place_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('title');
            $table->foreignId('category_id')->nullable()->constrained('locations_category');
            $table->text('description');
            $table->decimal('lat', 10, 7);
            $table->decimal('long', 10, 7);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_place_requests');
    }
};
