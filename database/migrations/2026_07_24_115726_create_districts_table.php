<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The districts table BACKEND.md proposed (mirror of locations_category:
     * id, title, status) — approved by the user on 2026-07-24. Ships with the
     * 11 Tekirdağ districts inline because the FK below needs them to exist:
     * every locations row already references district ids 1–11. Districts
     * travel in the bootstrap payload, so the table also gets the
     * map_version trigger (the function exists — created in the
     * create_map_version_table migration, which runs earlier).
     */
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('active');
        });

        DB::table('districts')->insert([
            ['id' => 1, 'title' => 'Süleymanpaşa', 'status' => 'active'],
            ['id' => 2, 'title' => 'Çorlu', 'status' => 'active'],
            ['id' => 3, 'title' => 'Çerkezköy', 'status' => 'active'],
            ['id' => 4, 'title' => 'Şarköy', 'status' => 'active'],
            ['id' => 5, 'title' => 'Malkara', 'status' => 'active'],
            ['id' => 6, 'title' => 'Marmaraereğlisi', 'status' => 'active'],
            ['id' => 7, 'title' => 'Kapaklı', 'status' => 'active'],
            ['id' => 8, 'title' => 'Hayrabolu', 'status' => 'active'],
            ['id' => 9, 'title' => 'Muratlı', 'status' => 'active'],
            ['id' => 10, 'title' => 'Saray', 'status' => 'active'],
            ['id' => 11, 'title' => 'Ergene', 'status' => 'active'],
        ]);
        DB::statement("SELECT setval(pg_get_serial_sequence('districts','id'), (SELECT MAX(id) FROM districts))");

        Schema::table('locations', function (Blueprint $table) {
            $table->foreign('district_id')->references('id')->on('districts');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER districts_bump_map_version
                AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON districts
                FOR EACH STATEMENT EXECUTE FUNCTION bump_map_version();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS districts_bump_map_version ON districts');
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
        });
        Schema::dropIfExists('districts');
    }
};
