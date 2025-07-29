<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Urutan pemanggilan ini sangat penting
        $this->call([
            RoleSeeder::class,      // 1. Ini membuat data peran ('super_admin', dll)
            DivisionSeeder::class,  // 2. Ini membuat data divisi
            UserSeeder::class, 
            TestUserSeeder::class,     // 3. Baru ini membuat user super admin
        ]);
    }
}