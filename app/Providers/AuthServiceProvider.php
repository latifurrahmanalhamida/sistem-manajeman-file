<?php

namespace App\Providers;

use App\Models\User;
use App\Models\File; // Tambahkan import untuk File
use App\Models\Folder;
use App\Policies\FolderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\UserPolicy;
use App\Policies\FilePolicy; // Tambahkan import untuk FilePolicy

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        File::class => FilePolicy::class,
        User::class => UserPolicy::class, // <-- BARIS INI DITAMBAHKAN
        Folder::class => FolderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Baris Gate::define di bawah ini tidak sepenuhnya benar
        // karena $user->role adalah objek, bukan string.
        // Sebaiknya periksa nama role seperti ini:
        Gate::define('superadmin', fn (User $user) => $user->role->name === 'super_admin');
    }
}