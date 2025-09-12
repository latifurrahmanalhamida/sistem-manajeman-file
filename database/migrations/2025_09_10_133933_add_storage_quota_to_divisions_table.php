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
        Schema::table('divisions', function (Blueprint $table) {
            // Menambahkan kolom untuk kuota penyimpanan setelah kolom 'name'
            // Tipe BIGINT untuk menampung angka besar (bytes)
            // Default 0 berarti tidak ada batasan (unlimited)
            $table->bigInteger('storage_quota')->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            // Hapus kolom jika migrasi di-rollback
            $table->dropColumn('storage_quota');
        });
    }
};