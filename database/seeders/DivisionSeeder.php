<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hapus data lama untuk menghindari duplikasi
        DB::table('divisions')->delete();
        
        // Contoh data divisi, silakan sesuaikan
        $divisions = [
            ['name' => 'Keuangan'],
            ['name' => 'Pemasaran'],
            ['name' => 'Operasional'],
        ];

        // Masukkan data ke tabel divisions
        DB::table('divisions')->insert($divisions);
    }
}