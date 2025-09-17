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
            'division_id' => $division->id,
            'action' => 'Membuat Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => "Divisi baru '{$division->name}' berhasil dibuat."],
            'status' => 'Berhasil',
        ]);
    }

public function updated(Division $division): void
{
    // DAFTAR ABAIKAN: Kolom yang tidak perlu dicatat perubahannya.
    $ignoreFields = ['updated_at', 'created_at'];

    // PEMETAAN NAMA: Terjemahkan nama kolom teknis ke nama yang mudah dibaca.
    $fieldNames = [
        'name' => 'Nama Divisi',
        'storage_quota' => 'Kuota Penyimpanan',
        // Tambahkan pemetaan lain jika ada...
    ];

    $details = [];
    $dirty = $division->getDirty();

    foreach ($dirty as $field => $newValue) {
        // Lewati kolom yang ada di dalam daftar $ignoreFields
        if (in_array($field, $ignoreFields)) {
            continue;
        }

        $oldValue = $division->getOriginal($field);

        // Gunakan nama yang mudah dibaca dari pemetaan, jika tidak ada, gunakan nama aslinya.
        $fieldName = $fieldNames[$field] ?? $field;

        // KHUSUS UNTUK KUOTA: Ubah format byte menjadi GB
        if ($field === 'storage_quota') {
            $oldValue = $this->formatBytes($oldValue);
            $newValue = $this->formatBytes($newValue);
        }

        $details[] = "• **{$fieldName}**: dari '{$oldValue}' menjadi '{$newValue}'";
    }

    if (!empty($details)) {
        $infoMessage = "Data divisi '{$division->name}' telah diperbarui dengan rincian:\n" . implode("\n", $details);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'division_id' => $division->id,
            'action' => 'Mengubah Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => $infoMessage],
            'status' => 'Berhasil',
        ]);
    }
}

private function formatBytes($bytes, $precision = 2): string
{
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow)); // 1 << (10 * $pow) is equivalent to pow(1024, $pow)
    return round($bytes, $precision) . ' ' . $units[$pow];
}

    public function deleted(Division $division): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'division_id' => $division->id,
            'action' => 'Menghapus Divisi',
            'target_type' => get_class($division),
            'target_id' => $division->id,
            'details' => ['info' => "Divisi '{$division->name}' telah dihapus."],
            'status' => 'Berhasil',
        ]);
    }
}