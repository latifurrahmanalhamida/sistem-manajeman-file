<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    // ... kode yang sudah ada ...
    
    // Tambahkan ini agar bisa diisi massal
    protected $fillable = [
        'nama_file_asli',
        'nama_file_tersimpan',
        'path_penyimpanan',
        'tipe_file',
        'ukuran_file',
        'uploader_id',
        'division_id',
    ];


    /**
     * Mendapatkan user yang mengunggah file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Mendapatkan divisi dari file ini.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}