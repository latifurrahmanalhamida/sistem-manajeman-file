<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Folder;
use App\Models\Division; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Rename file
     */
    public function rename(Request $request, $fileId)
    {
        $request->validate([
            'new_name' => 'required|string|max:255',
        ]);
        $file = File::findOrFail($fileId);
        $this->authorize('update', $file);
        $newName = $request->input('new_name');
        // Cek apakah nama baru sudah dipakai file lain di divisi yang sama
        $exists = File::where('nama_file_asli', $newName)
            ->where('division_id', $file->division_id)
            ->where('id', '!=', $fileId)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Nama file sudah digunakan di divisi ini.'], 409);
        }
        $file->nama_file_asli = $newName;
        $file->save();
        $file->load('uploader:id,name');
        return response()->json(['message' => 'Nama file berhasil diubah.', 'file' => $file]);
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $folderId = $request->query('folder_id');

        // Determine which division to show
        $targetDivisionId = $user->division_id; // Default to user's own division

        // Allow super_admin to switch division view
        if ($user->role->name === 'super_admin' && $request->has('division_id') && $request->query('division_id') !== '') {
            $targetDivisionId = $request->query('division_id');
        } else {
            $targetDivisionId = $user->division_id;
        }

        // --- PERBAIKAN DI SINI: Tambahkan withSum untuk folder ---
        $foldersQuery = Folder::query()
            ->with('user:id,name')
            ->withSum('files', 'ukuran_file') // Menghitung total ukuran file di dalam folder
            ->when(is_null($folderId), function ($q) {
                $q->whereNull('parent_folder_id');
            }, function ($q) use ($folderId) {
                $q->where('parent_folder_id', $folderId);
            });

        $filesQuery = File::query()->with('uploader:id,name')
            ->when(is_null($folderId), function ($q) {
                $q->whereNull('folder_id');
            }, function ($q) use ($folderId) {
                $q->where('folder_id', $folderId);
            });

        // Apply division filtering for all roles
        if ($targetDivisionId) {
            $foldersQuery->where('division_id', $targetDivisionId);
            $filesQuery->where('division_id', $targetDivisionId);
        }

        $currentFolder = $folderId ? Folder::with('parent')->find($folderId) : null;
        $breadcrumbs = collect();
        if ($currentFolder) {
            $current = $currentFolder;
            while ($current) {
                $breadcrumbs->prepend($current->only(['id','name']));
                $current = $current->parent;
            }
        }

        return response()->json([
            'folders' => $foldersQuery->latest()->get(),
            'files'   => $filesQuery->latest()->get(),
            'breadcrumbs' => $breadcrumbs->values(),
            'current_folder' => $currentFolder ? $currentFolder->only(['id','name','parent_folder_id']) : null,
        ]);
    }

public function store(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:512000|mimes:mp4,mp3,wav,csv,xml,json,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar',
        'new_name' => 'nullable|string|max:255',
        'folder_id' => 'nullable|integer|exists:folders,id',
        'division_id' => 'nullable|integer|exists:divisions,id', // tambahan dari superadmin-dashboard
    ]);

    $uploadedFile = $request->file('file');
    $originalName = $uploadedFile->getClientOriginalName();
    $newName = $request->input('new_name');
    $overwrite = $request->boolean('overwrite');
    $folderId = $request->input('folder_id');

    $user = Auth::user();

    // --- LOGIKA DIVISION ID ---
    $divisionId = null;
    if ($user->role->name === 'super_admin') {
        if ($folderId) {
            $folder = Folder::find($folderId);
            if ($folder) {
                $divisionId = $folder->division_id;
            }
        } elseif ($request->has('division_id')) {
            $divisionId = $request->input('division_id');
        }
    } else {
        if (!$user->division_id) {
            return response()->json(['message' => 'Anda tidak terdaftar di divisi manapun.'], 403);
        }
        $divisionId = $user->division_id;
    }

    if (is_null($divisionId)) {
        return response()->json(['message' => 'Gagal menentukan divisi untuk file ini.'], 422);
    }

    $division = Division::find($divisionId);

    // --- CEK KUOTA ---
    if ($user->role->name !== 'super_admin' && $division && $division->storage_quota > 0) {
        $currentSize = $division->files()->sum('ukuran_file');
        $newFileSize = $uploadedFile->getSize();

        if (($currentSize + $newFileSize) > $division->storage_quota) {
            return response()->json([
                'message' => 'Gagal mengunggah file !! Batas penyimpanan untuk divisi Anda telah tercapai, segera hubungi admin divisi anda.'
            ], 403);
        }
    }

    // --- LOGIKA SIMPAN FILE ---
    $fileNameToSave = $newName ?: $originalName;

    $existingFile = File::where('nama_file_asli', $fileNameToSave)
        ->where('division_id', $divisionId)
        ->where('folder_id', $folderId)
        ->first();

    if ($existingFile && !$overwrite) {
        return response()->json([
            'message' => 'File dengan nama "' . $fileNameToSave . '" sudah ada di lokasi ini.',
            'status' => 'conflict'
        ], 409);
    }

    if ($existingFile && $overwrite) {
        Storage::delete($existingFile->path_penyimpanan);
        $existingFile->forceDelete();
    }

    // Tentukan path penyimpanan
    $uploadPath = 'uploads/' . Str::slug($division->name, '-');
    if ($folderId) {
        $folder = Folder::with('division', 'parent')->find($folderId);
        if ($folder && $folder->division_id != $divisionId) {
            return response()->json(['message' => 'Folder tujuan tidak cocok dengan divisi yang dipilih.'], 422);
        }
        if ($folder) {
            $uploadPath = $folder->getFullPath();
        }
    }

    $path = $uploadedFile->store($uploadPath);

    $newFile = File::create([
        'nama_file_asli' => $fileNameToSave,
        'nama_file_tersimpan' => $uploadedFile->hashName(),
        'path_penyimpanan' => $path,
        'tipe_file' => $uploadedFile->getClientMimeType(),
        'ukuran_file' => $uploadedFile->getSize(),
        'uploader_id' => $user->id,
        'division_id' => $divisionId,
        'folder_id' => $folderId,
    ]);

    return response()->json(['message' => 'File berhasil diunggah.', 'file' => $newFile], 201);
}

    public function recent()
    {
        $user = Auth::user();
        $query = File::where('created_at', '>=', now()->subDays(7))
            ->with('uploader:id,name', 'division:id,name');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }
        return $query->latest()->get();
    }

    public function allFiles()
    {
        $user = Auth::user();
        $query = File::with('uploader:id,name', 'division:id,name');

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
        $query = File::onlyTrashed()->with('uploader:id,name', 'division:id,name');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }
        return $query->latest()->get();
    }

    public function toggleFavorite($fileId)
    {
        $file = File::withTrashed()->findOrFail($fileId);
        $this->authorize('update', $file);
        $file->is_favorited = !$file->is_favorited;
        $file->save();
        return response()->json(['message' => 'Status favorit berhasil diubah.', 'file' => $file]);
    }

    public function restore(Request $request, $fileId)
    {
        $file = File::onlyTrashed()->findOrFail($fileId);
        $this->authorize('restore', $file);

        $newName = $request->input('new_name');
        $overwrite = $request->boolean('overwrite');
        $fileNameToCheck = $newName ?: $file->nama_file_asli;

        $existingActiveFile = File::where('nama_file_asli', $fileNameToCheck)
                                  ->where('division_id', $file->division_id)
                                  ->first();

        if ($existingActiveFile && !$overwrite) {
            return response()->json([
                'message' => 'File dengan nama "' . $fileNameToCheck . '" sudah ada di lokasi tujuan.',
                'status' => 'conflict'
            ], 409);
        }

        if ($existingActiveFile && $overwrite) {
            Storage::delete($existingActiveFile->path_penyimpanan);
            $existingActiveFile->forceDelete();
        }
        
        $file->restore();

        if ($newName) {
            $file->nama_file_asli = $newName;
            $file->save();
        }

        return response()->json(['message' => 'File berhasil dipulihkan.']);
    }

    public function forceDelete($fileId)
    {
        $file = File::onlyTrashed()->findOrFail($fileId);
        $this->authorize('forceDelete', $file);
        Storage::delete($file->path_penyimpanan);
        $file->forceDelete();
        return response()->json(['message' => 'File berhasil dihapus permanen.']);
    }

    public function download($fileId)
    {
        $file = File::withTrashed()->findOrFail($fileId);
        $this->authorize('view', $file);

        if (!Storage::exists($file->path_penyimpanan)) {
            return response()->json(['message' => 'File tidak ditemukan di server.'], 404);
        }

        $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $textMimeTypes = ['text/plain', 'application/pdf'];

        if (in_array($file->tipe_file, $imageMimeTypes) || in_array($file->tipe_file, $textMimeTypes)) {
            return Storage::response($file->path_penyimpanan, $file->nama_file_asli);
        }

        return Storage::download($file->path_penyimpanan, $file->nama_file_asli);
    }

    public function destroy($fileId)
    {
        $file = File::findOrFail($fileId);
        $this->authorize('delete', $file);
        $file->delete();
        return response()->json(['message' => 'File berhasil dipindahkan ke sampah.']);
    }
}