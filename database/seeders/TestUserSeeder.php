<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil data role dan divisi yang diperlukan
        $adminRole = Role::where('name', 'admin_devisi')->first();
        $userRole = Role::where('name', 'user_devisi')->first();
        $financeDivision = Division::where('name', 'Keuangan')->first();

        // Pastikan role dan divisi ada sebelum membuat user
        if ($adminRole && $userRole && $financeDivision) {
            // 1. Buat Admin Devisi Keuangan
            User::firstOrCreate(
                ['email' => 'admin.keuangan@contoh.com'],
                [
                    'nipp' => '12345', 
                    'name' => 'Admin Keuangan',
                    'username' => 'admin.keu',
                    'password' => Hash::make('password'),
                    'role_id' => $adminRole->id,
                    'division_id' => $financeDivision->id,
                ]
            );

            // 2. Buat User Devisi Keuangan
            User::firstOrCreate(
                ['email' => 'user.keuangan@contoh.com'],
                [
                    'nipp' => '54321',
                    'name' => 'User Keuangan',
                    'username' => 'user.keu',
                    'password' => Hash::make('password'),
                    'role_id' => $userRole->id,
                    'division_id' => $financeDivision->id,
                ]
            );
        }
    }
}