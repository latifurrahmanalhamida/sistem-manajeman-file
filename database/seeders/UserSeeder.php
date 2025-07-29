<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil role 'super_admin' yang sudah dibuat oleh RoleSeeder
        $superadminRole = Role::where('name', 'super_admin')->first();

        // 2. Buat pengguna Super Admin
        // Pastikan tidak ada duplikasi email
        User::firstOrCreate(
            ['email' => 'admin@contoh.com'], // Kunci untuk mencari
            [
                'name' => 'Admin Utama',
                'password' => Hash::make('password'), // Ganti 'password' dengan yang aman
                'role_id' => $superadminRole->id, // Tetapkan role_id
                'division_id' => null, // Super Admin tidak punya divisi
            ]
        );
    }
}