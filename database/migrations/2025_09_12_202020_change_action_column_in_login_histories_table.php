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
        Schema::table('login_histories', function (Blueprint $table) {
            // Mengubah tipe kolom 'action' menjadi STRING (VARCHAR 255)
            $table->string('action')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_histories', function (Blueprint $table) {
            // Jika di-rollback, kembalikan ke tipe sebelumnya
            // Ganti 'enum' dengan tipe data asli Anda jika berbeda
            $table->enum('action', ['login', 'logout'])->change();
        });
    }
};