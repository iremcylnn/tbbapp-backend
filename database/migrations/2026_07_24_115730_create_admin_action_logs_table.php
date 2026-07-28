<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for admin actions (ported from the old server, including its
     * reasoning): the shared x-admin-key can't answer "who approved, when" —
     * this records at least the action + time + IP. When real admin identities
     * arrive, an admin_id column slots in without restructuring.
     *
     * Append-only: rows are never updated (created_at only, no updated_at).
     */
    public function up(): void
    {
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('ip_address')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};
