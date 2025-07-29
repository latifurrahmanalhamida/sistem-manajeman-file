<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hapus data lama untuk menghindari duplikasi
        DB::table('roles')->delete();

        // Data peran yang akan dimasukkan
        $roles = [
            ['name' => 'super_admin'], // Nama dengan underscore
            ['name' => 'admin_devisi'],
            ['name' => 'user_devisi'],
        ];

        // Masukkan data ke tabel roles
        DB::table('roles')->insert($roles);
    }
}