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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file_asli');
            $table->string('nama_file_tersimpan');
            $table->string('path_penyimpanan');
            $table->string('tipe_file')->nullable();
            $table->unsignedBigInteger('ukuran_file');
            $table->foreignId('uploader_id')->constrained('users');

            // Pastikan kolom ini ada dan terhubung ke tabel divisions
            $table->foreignId('division_id')->nullable()->constrained('divisions');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};