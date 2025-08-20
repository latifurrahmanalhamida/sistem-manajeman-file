<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- 1. Tambahkan ini

class File extends Model
{
    use HasFactory, SoftDeletes; // <-- 2. Tambahkan ini

    protected $fillable = [
        'nama_file_asli',
        'nama_file_tersimpan',
        'path_penyimpanan',
        'tipe_file',
        'ukuran_file',
        'uploader_id',
        'division_id',
        'folder_id',
        'is_favorited', // <-- 3. Tambahkan ini agar bisa diisi
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}