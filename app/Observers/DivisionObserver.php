<?php

namespace App\Observers;

use App\Models\Division;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DivisionObserver
{
    public $afterCommit = true;

    public function created(Division $division): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Membuat Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => "Divisi baru '{$division->name}' berhasil dibuat."],
            'status' => 'Berhasil',
        ]);
    }

    public function updated(Division $division): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Mengubah Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => "Data divisi '{$division->name}' telah diperbarui."],
            'status' => 'Berhasil',
        ]);
    }

    public function deleted(Division $division): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Menghapus Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => "Divisi '{$division->name}' telah dihapus."],
            'status' => 'Berhasil',
        ]);
    }
}