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
    Schema::create('activity_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
        $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('cascade');
        $table->string('action'); // Contoh: 'Membuat Pengguna', 'Mengunggah File'
        $table->morphs('target'); // Kolom untuk target (bisa User, File, Divisi, dll.)
        $table->json('details')->nullable(); // Untuk detail tambahan
        $table->string('status'); // Contoh: 'Berhasil', 'Gagal'
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
