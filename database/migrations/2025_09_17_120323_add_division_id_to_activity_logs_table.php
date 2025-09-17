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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('division_id')
                ->nullable() // Dibuat nullable jika ada log sistem tanpa divisi
                ->after('user_id')
                ->constrained('divisions') // Menghubungkan ke tabel 'divisions'
                ->onDelete('cascade'); // Jika divisi dihapus, log terkait juga terhapus
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            //
        });
    }
};
