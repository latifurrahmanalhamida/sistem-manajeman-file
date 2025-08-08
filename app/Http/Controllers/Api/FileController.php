<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;


class FileController extends Controller
{
    // index() dan store() tetap sama

    public function index()
    {
        $user = Auth::user();
        $query = File::query()->with('uploader:id,name', 'division:id,name');
        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', 'like', $user->division_id);
        }
        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // max 10MB
            'parent_id' => 'nullable|exists:files,id,deleted_at,NULL', // Memastikan parent_id ada dan bukan file yang terhapus
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $user = Auth::user();
        $divisionId = $user->division_id;
        $overwrite = $request->boolean('overwrite');

        // Cek file berdasarkan nama asli dan ID divisi
        $existingFile = File::where('nama_file_asli', $originalName)
                            ->where('division_id', $divisionId)
                            ->first();

        if ($existingFile && !$overwrite) {
            // Skenario 2: File ada, tapi belum ada konfirmasi.
            return response()->json([
                'message' => 'File dengan nama yang sama sudah ada di lokasi ini.',
                'status' => 'conflict'
            ], 409); // HTTP 409 Conflict
        }

        if ($existingFile && $overwrite) {
            // Skenario 3: Pengguna setuju menimpa.
            // Hapus file lama dari storage
            Storage::delete($existingFile->path_penyimpanan);
            // Hapus record lama dari database (force delete karena akan diganti)
            $existingFile->forceDelete();
        }

        // Skenario 1 & 3: Simpan file baru.
        $path = $uploadedFile->store('uploads/' . $divisionId);

        $newFile = File::create([
            'nama_file_asli' => $originalName,
            'nama_file_tersimpan' => $uploadedFile->hashName(),
            'path_penyimpanan' => $path,
            'tipe_file' => $uploadedFile->getClientMimeType(),
            'ukuran_file' => $uploadedFile->getSize(),
            'uploader_id' => $user->id,
            'division_id' => $divisionId,
            // 'parent_id' akan ditambahkan jika Anda mengelola struktur folder
        ]);

        return response()->json(['message' => 'File berhasil diunggah.', 'file' => $newFile], 201);
    }

    // --- FUNGSI BARU DIMULAI DARI SINI ---

    public function recent()
    {
        // Sama seperti index, tapi hanya mengambil file 7 hari terakhir
        $user = Auth::user();
        $query = File::where('created_at', '>=', now()->subDays(7))
            ->with('uploader:id,name', 'division:id,name');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }
        return $query->latest()->get();
    }

    public function favorites()
    {
        $user = Auth::user();
        $query = File::where('is_favorited', true)
            ->with('uploader:id,name', 'division:id,name');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }
        return $query->latest()->get();
    }

    public function trashed()
    {
        $user = Auth::user();
        // onlyTrashed() hanya akan mengambil file yang sudah di-soft delete
        $query = File::onlyTrashed()->with('uploader:id,name', 'division:id,name');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }
        return $query->latest()->get();
    }

    public function toggleFavorite(File $file)
    {
        $this->authorize('update', $file); // Memastikan user punya akses ke file ini
        $file->is_favorited = !$file->is_favorited;
        $file->save();
        return response()->json(['message' => 'Status favorit berhasil diubah.', 'file' => $file]);
    }

    public function restore($fileId)
    {
        $file = File::onlyTrashed()->findOrFail($fileId);
        $this->authorize('restore', $file);
        $file->restore();
        return response()->json(['message' => 'File berhasil dipulihkan.']);
    }

    public function forceDelete($fileId)
    {
        $file = File::onlyTrashed()->findOrFail($fileId);
        $this->authorize('forceDelete', $file);
        Storage::delete($file->path_penyimpanan); // Hapus file fisik
        $file->forceDelete(); // Hapus permanen dari database
        return response()->json(['message' => 'File berhasil dihapus permanen.']);
    }

    // GANTI FUNGSI LAMA DENGAN YANG INI
    public function download(File $file)
    {
        // Otorisasi: Pastikan pengguna punya hak akses
        $this->authorize('view', $file);

        // Cek apakah file fisik ada di server
        if (!Storage::exists($file->path_penyimpanan)) {
            return response()->json(['message' => 'File tidak ditemukan di server.'], 404);
        }

        // Logika Cerdas: Tentukan apakah file akan dipratinjau atau diunduh
        $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $textMimeTypes = ['text/plain', 'application/pdf'];

        // Jika file adalah gambar atau PDF, tampilkan di browser (pratinjau)
        if (in_array($file->tipe_file, $imageMimeTypes) || in_array($file->tipe_file, $textMimeTypes)) {
            // Storage::response() mengirim file dengan header 'inline'
            return Storage::response($file->path_penyimpanan, $file->nama_file_asli);
        }

        // Untuk semua tipe file lainnya, paksa unduh
        // Storage::download() mengirim file dengan header 'attachment'
        return Storage::download($file->path_penyimpanan, $file->nama_file_asli);
    }


    // --- UBAH FUNGSI DESTROY ---
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);
        $file->delete(); // Ini akan melakukan soft delete, bukan hapus permanen
        return response()->json(['message' => 'File berhasil dipindahkan ke sampah.']);
    }

   public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // max 10MB
        ]);

        $user = Auth::user();
        $path = $request->file('file')->store('uploads/' . $user->division_id);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => url('/api/files/preview/' . $path),
        ]);
    }

    public function preview(File $file)
{
    // Verifikasi bahwa pengguna memiliki izin untuk melihat file ini
    // (Contoh: pengguna berada di divisi yang sama dengan file)
    if (auth()->user()->division_id !== $file->division_id && auth()->user()->role !== 'super_admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $path = "uploads/{$file->division_id}/{$file->nama_file_tersimpan}";

    if (!Storage::disk('local')->exists($path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    $stream = Storage::disk('local')->readStream($path);

    $headers = [
        'Content-Type' => $file->tipe_file,
        'Content-Disposition' => 'inline; filename="' . $file->nama_file_asli . '"',
    ];

    return new StreamedResponse(function() use ($stream) {
        fpassthru($stream);
    }, 200, $headers);
}
}
