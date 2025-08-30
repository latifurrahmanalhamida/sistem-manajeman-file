<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:run --only-files')->everyMinute();

    // backup setiap jam
    // $schedule->command('backup:run --only-db')->hourly();
    // $schedule->command('backup:run')->hourly();

    // atau jika mau tiap hari jam 2 pagi:
    // $schedule->command('backup:all')->dailyAt('02:00');
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
