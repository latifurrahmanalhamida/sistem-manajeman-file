<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        $request->validate(['file' => 'required|file|max:10240']);
        $user = Auth::user();
        $file = $request->file('file');
        $path = $file->store('uploads/' . $user->division_id);

        $newFile = File::create([
            'nama_file_asli' => $file->getClientOriginalName(),
            'nama_file_tersimpan' => $file->hashName(),
            'path_penyimpanan' => $path,
            'tipe_file' => $file->getClientMimeType(),
            'ukuran_file' => $file->getSize(),
            'uploader_id' => $user->id,
            'division_id' => $user->division_id,
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

    public function download(File $file)
    {
        $this->authorize('view', $file);
        if (!Storage::exists($file->path_penyimpanan)) {
            return response()->json(['message' => 'File tidak ditemukan di server.'], 404);
        }
        return Storage::download($file->path_penyimpanan, $file->nama_file_asli);
    }
    
    // --- UBAH FUNGSI DESTROY ---
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);
        $file->delete(); // Ini akan melakukan soft delete, bukan hapus permanen
        return response()->json(['message' => 'File berhasil dipindahkan ke sampah.']);
    }
}