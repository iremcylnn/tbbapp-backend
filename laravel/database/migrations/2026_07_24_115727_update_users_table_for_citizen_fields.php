<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The citizen auth contract (mirroring the old Node server) uses
     * firstName/lastName, not Laravel's single `name`. The stock users
     * migration has already run everywhere, so this ALTERs instead of
     * editing that file. The table is empty; non-nullable columns are safe.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('first_name');
            $table->string('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
            $table->string('name');
        });
    }
};
