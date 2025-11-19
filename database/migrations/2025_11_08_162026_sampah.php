<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sampah', function (Blueprint $table) {
            $table->boolean('simpan_berat')->default(true)->after('poin_per_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sampah', function (Blueprint $table) {
            $table->dropColumn('simpan_berat');
        });
    }
};
