<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Jangan lupa tambahkan ini

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update NIPP untuk user id 1 (Admin Utama)
        DB::table('users')->where('id', 1)->update(['nipp' => '71111']);

        // Update NIPP untuk user id 2 (Admin Keuangan)
        DB::table('users')->where('id', 2)->update(['nipp' => '72222']);

        // Update NIPP untuk user id 3 (User Keuangan)
        DB::table('users')->where('id', 3)->update(['nipp' => '73333']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dibiarkan kosong karena ini hanya update data
    }
};