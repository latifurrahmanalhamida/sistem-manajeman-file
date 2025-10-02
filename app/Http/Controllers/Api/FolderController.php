<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    /**
     * Menampilkan daftar folder berdasarkan parent folder.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
    $parentId = $request->query('parent_folder_id');

    $query = Folder::with('user:id,name')
               ->withSum('files', 'ukuran_file')
               ->where('parent_folder_id', $parentId);

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Menyimpan folder baru.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('folders')->where(function ($query) use ($user, $request) {
                    return $query->where('division_id', $user->division_id)
                                 ->where('parent_folder_id', $request->parent_folder_id);
                }),
            ],
            'parent_folder_id' => 'nullable|exists:folders,id',
        ]);

        $folder = Folder::create([
            'name' => $validated['name'],
            'division_id' => $user->division_id,
            'user_id' => $user->id,
            'parent_folder_id' => $validated['parent_folder_id'] ?? null,
        ]);

        // Buat direktori fisik
        $folder->loadMissing('division', 'parent');
        Storage::makeDirectory($folder->getFullPath());

        return response()->json($folder, 201);
    }

    /**
     * Menampilkan detail satu folder.
     */
    public function show(Folder $folder)
    {
        $this->authorize('view', $folder);
        $folder->load([
            'children' => fn($q) => $q->with('user:id,name')->withSum('files', 'ukuran_file'),
            'files' => fn($q) => $q->with('uploader:id,name'),
        ]);
        return response()->json($folder);
    }

    /**
     * Memperbarui nama folder.
     */
    public function update(Request $request, Folder $folder)
    {
        $this->authorize('update', $folder);
        $user = Auth::user();

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('folders')->where(function ($query) use ($user, $folder) {
                    return $query->where('division_id', $user->division_id)
                                 ->where('parent_folder_id', $folder->parent_folder_id);
                })->ignore($folder->id),
            ],
        ]);

        $folder->loadMissing('division', 'parent');
        $oldPath = $folder->getFullPath();

        // Find all files that need updating BEFORE the name is changed
        $files = File::where('path_penyimpanan', 'like', $oldPath . '%')->get();

        DB::beginTransaction();
        try {
            // 1. Update folder name in DB
            $folder->update(['name' => $validated['name']]);

            // 2. Get the new path
            $newPath = $folder->getFullPath();

            // 3. Move the physical directory
            Storage::move($oldPath, $newPath);

            // 4. Update file paths in DB
            foreach ($files as $file) {
                $newFilePath = str_replace($oldPath, $newPath, $file->path_penyimpanan);
                $file->update(['path_penyimpanan' => $newFilePath]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // If anything fails, move the directory back
            if (Storage::exists($newPath)) {
                Storage::move($newPath, $oldPath);
            }
            return response()->json(['message' => 'Gagal mengubah nama folder, terjadi kesalahan.', 'error' => $e->getMessage()], 500);
        }

        return response()->json($folder);
    }

    /**
     * Menghapus folder (soft delete).
     */
    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->files()->exists()) {
            return response()->json(['message' => 'Folder tidak dapat dihapus karena tidak kosong.'], 409);
        }

        // Get the path before deleting the record
        $folder->loadMissing('division', 'parent');
        $path = $folder->getFullPath();

        $folder->delete();

        // Delete the physical directory
        Storage::deleteDirectory($path);

        return response()->json(null, 204);
    }
}