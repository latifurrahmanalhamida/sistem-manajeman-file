<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [ \App\Models\File::class => \App\Policies\FilePolicy::class,
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Defines a 'superadmin' gate to check if a user has the 'superadmin' role.
        // This is used in your api.php routes.
        Gate::define('superadmin', fn (User $user) => $user->role === 'superadmin');
    }
    
}
