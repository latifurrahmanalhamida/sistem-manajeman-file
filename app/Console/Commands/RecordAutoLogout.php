<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LoginHistory;
use Carbon\Carbon;

class RecordAutoLogout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:record-auto-logout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mencatat auto-logout untuk sesi yang kedaluwarsa dan membersihkannya';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memeriksa sesi yang kedaluwarsa...');

        // Ambil waktu kedaluwarsa sesi dari konfigurasi (default 120 menit)
        $sessionLifetimeMinutes = config('session.lifetime');
        $expirationTime = Carbon::now()->subMinutes($sessionLifetimeMinutes);

        // Cari semua sesi yang sudah kedaluwarsa dan memiliki user_id
        $expiredSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '<', $expirationTime->getTimestamp())
            ->get();

        $loggedOutCount = 0;

        foreach ($expiredSessions as $session) {
            // Cek login terakhir user untuk memastikan kita tidak membuat duplikat log
            $lastLogin = LoginHistory::where('user_id', $session->user_id)
                ->where('action', 'login')
                ->latest()
                ->first();

            if ($lastLogin) {
                // Cek apakah sudah ada log logout setelah login terakhir
                $hasLogoutRecord = LoginHistory::where('user_id', $session->user_id)
                    ->whereIn('action', ['logout', 'auto-logout'])
                    ->where('created_at', '>', $lastLogin->created_at)
                    ->exists();
                
                // Jika BELUM ADA record logout, maka buat satu
                if (!$hasLogoutRecord) {
                    LoginHistory::create([
                        'user_id' => $session->user_id,
                        'action' => 'auto-logout', // Menggunakan aksi 'auto-logout'
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'created_at' => Carbon::createFromTimestamp($session->last_activity)->toDateTimeString(),
                        'updated_at' => Carbon::createFromTimestamp($session->last_activity)->toDateTimeString(),
                    ]);
                    $loggedOutCount++;
                }
            }

            // Hapus sesi yang sudah kedaluwarsa dari database
            DB::table('sessions')->where('id', $session->id)->delete();
        }

        $this->info("Selesai. {$loggedOutCount} auto-logout berhasil dicatat dan sesinya dibersihkan.");
        return 0;
    }
}