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
        Schema::table('files', function (Blueprint $table) {
            // Menambahkan kolom folder_id setelah kolom division_id
            // nullable() -> Boleh kosong (untuk file di root)
            // constrained('folders') -> Terhubung ke tabel folders
            // onDelete('cascade') -> Jika folder dihapus, file di dalamnya ikut terhapus
            $table->foreignId('folder_id')
                  ->nullable()
                  ->after('division_id') // Opsional: menempatkan kolom agar rapi
                  ->constrained('folders')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Menghapus foreign key constraint sebelum menghapus kolom
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
    }
};