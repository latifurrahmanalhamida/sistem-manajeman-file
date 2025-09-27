<?php

namespace App\Providers;

// --- TAMBAHKAN IMPORT BARU INI ---
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
// ------------------------------------

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// --- TAMBAHKAN IMPORT OBSERVER ---
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\File;
use App\Observers\FileObserver;
use App\Observers\DivisionObserver;
use App\Models\Division;
use App\Observers\FolderObserver;
use App\Models\Folder;

// --- TAMBAHKAN IMPORT LISTENER BARU ---
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
// ---------------------------------------

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // --- TAMBAHKAN KODE BARU DI SINI ---
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Logout::class => [
            LogSuccessfulLogout::class,
        ],
        // ------------------------------------
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        File::observe(FileObserver::class);
        Division::observe(DivisionObserver::class);
        Folder::observe(FolderObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}