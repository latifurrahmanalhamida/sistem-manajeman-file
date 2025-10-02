<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use App\Services\BackupService;

// class BackupAllCommand extends Command
// {
//     protected $signature = 'backup:all';
//     protected $description = 'Backup database + storage files';

//     public function handle(BackupService $backupService)
//     {
//         $this->info('🚀 Mulai backup full...');
//         if ($backupService->backupAll()) {
//             $this->info('✅ Backup selesai!');
//         } else {
//             $this->error('❌ Backup gagal!');
//         }
//     }
// }
