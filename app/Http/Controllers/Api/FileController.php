<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Menampilkan daftar file berdasarkan hak akses user.
     */
    public function index()
    {
        $user = Auth::user();

        $query = File::query()->with('uploader:id,name', 'division:id,name');

        // Jika user bukan super_admin, filter file berdasarkan divisinya
        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }

        $files = $query->latest()->get();

        return response()->json($files);
    }

    /**
     * Menyimpan file yang baru diunggah.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Maksimal 10MB
        ]);

        $user = Auth::user();
        $file = $request->file('file');

        // Simpan file ke storage/app/uploads/{division_id}
        // Nama file akan di-generate secara acak oleh Laravel untuk keamanan
        $path = $file->store('uploads/' . $user->division_id);

        // Simpan informasi file ke database
        $newFile = File::create([
            'nama_file_asli' => $file->getClientOriginalName(),
            'nama_file_tersimpan' => $file->hashName(),
            'path_penyimpanan' => $path,
            'tipe_file' => $file->getClientMimeType(),
            'ukuran_file' => $file->getSize(),
            'uploader_id' => $user->id,
            'division_id' => $user->division_id,
        ]);

        return response()->json([
            'message' => 'File berhasil diunggah.',
            'file' => $newFile
        ], 201); // 201 artinya Created
    }

    /**
     * Mengunduh file yang dipilih.
     */
    public function download(File $file)
    {
        $user = Auth::user();

        // Security Check: User hanya boleh download file dari divisinya, kecuali Super Admin
        if ($user->role->name !== 'super_admin' && $user->division_id !== $file->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Cek apakah file fisik ada di storage
        if (!Storage::exists($file->path_penyimpanan)) {
            return response()->json(['message' => 'File tidak ditemukan di server.'], 404);
        }

        // Kirim file sebagai download
        return Storage::download($file->path_penyimpanan, $file->nama_file_asli);
    }

    /**
     * Menghapus file.
     */
    public function destroy(File $file)
    {
        $user = Auth::user();

        // Security Check: Hanya Admin Devisi atau Super Admin yang bisa menghapus
        if ($user->role->name === 'user_devisi') {
             return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Security Check tambahan untuk Admin Devisi
        if ($user->role->name === 'admin_devisi' && $user->division_id !== $file->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Hapus file fisik dari storage
        Storage::delete($file->path_penyimpanan);

        // Hapus record file dari database
        $file->delete();

        return response()->json(['message' => 'File berhasil dihapus.']);
    }
}