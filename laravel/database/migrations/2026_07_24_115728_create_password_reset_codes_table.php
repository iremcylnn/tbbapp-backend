<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mobile-friendly password reset: a 6-digit code emailed to the user
     * (old server's flow, ported verbatim). Codes are stored HASHED — a
     * database leak must not leak usable codes. `used_at` doubles as the
     * invalidation mark: issuing a new code stamps unexpired old ones.
     *
     * (Laravel's stock password_reset_tokens table serves its link-based
     * web flow; it stays unused — this table is the app's flow.)
     */
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
