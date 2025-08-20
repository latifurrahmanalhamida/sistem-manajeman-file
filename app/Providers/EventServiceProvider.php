<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// --- TAMBAHKAN IMPORT INI ---
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\File;
use App\Observers\FileObserver;
use App\Observers\DivisionObserver;
use App\Models\Division;
use App\Observers\FolderObserver;
use App\Models\Folder;
// -----------------------------

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
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // --- TAMBAHKAN KODE PENDAFTARAN OBSERVER DI SINI ---
        User::observe(UserObserver::class);
        File::observe(FileObserver::class);
        Division::observe(DivisionObserver::class);
        Folder::observe(FolderObserver::class);
        // ----------------------------------------------------
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}