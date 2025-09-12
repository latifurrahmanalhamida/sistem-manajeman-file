<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    // --- 1. TAMBAHKAN BAGIAN INI UNTUK MENDAFTARKAN COMMAND BARU ---
    protected $commands = [
        Commands\RecordAutoLogout::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Ini adalah jadwal backup Anda yang sudah ada, jangan diubah
        $schedule->command('backup:run --only-files')->everyMinute();

        // --- 2. TAMBAHKAN JADWAL BARU DI SINI ---
        // Jalankan command auto-logout setiap lima menit
        $schedule->command('app:record-auto-logout')->everyFiveMinutes();
    }

    protected function scheduleTimezone()
    {
        return 'Asia/Jakarta';
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}