<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    /**
     * Tampilkan daftar folder berdasarkan parent dan divisi pengguna.
     * Query param: parent_id (nullable untuk root)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $parentId = $request->input('parent_id');
        if ($parentId === '') {
            $parentId = null;
        }

        $query = Folder::with('user:id,name')
            ->withSum('files', 'ukuran_file')
            ->when(is_null($parentId), function ($q) {
                $q->whereNull('parent_folder_id');
            }, function ($q) use ($parentId) {
                $q->where('parent_folder_id', $parentId);
            });

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Simpan folder baru.
     * Body: name (required), parent_id (nullable)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $this->authorize('create', Folder::class);
        $parentId = $request->input('parent_id');
        if ($parentId === '') {
            $parentId = null;
        }

        // Normalisasi parent_id di request agar validasi konsisten
        $request->merge(['parent_id' => $parentId]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Unik pada kombinasi (division_id, parent_folder_id, deleted_at null)
                Rule::unique('folders', 'name')->where(function ($q) use ($user, $parentId) {
                    return $q->where('division_id', $user->division_id)
                             ->where('parent_folder_id', $parentId)
                             ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
        ]);

        // Validasi parent (jika ada) harus berada pada divisi yang sama (kecuali super_admin)
        if ($parentId) {
            $parent = Folder::findOrFail($parentId);
            if ($user->role->name !== 'super_admin' && $parent->division_id !== $user->division_id) {
                return response()->json(['message' => 'Parent folder berada di divisi berbeda.'], 422);
            }
        }

        $folder = Folder::create([
            'name' => $validated['name'],
            'division_id' => $user->division_id,
            'user_id' => $user->id,
            'parent_folder_id' => $parentId,
        ]);

        return response()->json([
            'message' => 'Folder berhasil dibuat.',
            'data' => $folder,
        ], 201);
    }

    /**
     * Detail folder (opsional dipakai untuk validasi akses/navigasi).
     */
    public function show(Folder $folder)
    {
        $this->authorize('view', $folder);

        $folder->load([
            'children:id,name,user_id,updated_at,parent_folder_id,division_id',
            'children.user:id,name',
            'files:id,nama_file_asli,uploader_id,updated_at,ukuran_file,folder_id,division_id',
            'files.uploader:id,name'
        ]);

        $breadcrumbs = collect();
        $current = $folder;
        while ($current) {
            $breadcrumbs->prepend($current->only(['id','name']));
            $current = $current->parent;
        }

        return response()->json([
            'folder' => $folder,
            'breadcrumbs' => $breadcrumbs->values(),
        ]);
    }

    /**
     * Update nama dan/atau pindahkan parent folder.
     * Body: name (optional), parent_id (optional)
     */
    public function update(Request $request, Folder $folder)
    {
        $user = Auth::user();
        if ($user->role->name !== 'super_admin' && $folder->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        $this->authorize('update', $folder);

        $parentId = $request->has('parent_id') ? $request->input('parent_id') : $folder->parent_folder_id;
        if ($parentId === '') {
            $parentId = null;
        }

        // Normalisasi parent_id agar validasi konsisten
        if ($request->has('parent_id')) {
            $request->merge(['parent_id' => $parentId]);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('folders', 'name')
                    ->ignore($folder->id)
                    ->where(function ($q) use ($folder, $parentId) {
                        return $q->where('division_id', $folder->division_id)
                                 ->where('parent_folder_id', $parentId)
                                 ->whereNull('deleted_at');
                    }),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('folders', 'id')],
        ]);

        // Validasi parent (jika diganti) harus satu divisi
        if ($request->has('parent_id')) {
            if ($parentId) {
                $parent = Folder::findOrFail($parentId);
                if ($parent->division_id !== $folder->division_id && $user->role->name !== 'super_admin') {
                    return response()->json(['message' => 'Parent folder berada di divisi berbeda.'], 422);
                }
            }
            $folder->parent_folder_id = $parentId;
        }

        if ($request->has('name')) {
            $folder->name = $validated['name'];
        }

        $folder->save();

        return response()->json([
            'message' => 'Folder berhasil diperbarui.',
            'data' => $folder,
        ]);
    }

    /**
     * Hapus (soft delete) folder.
     */
    public function destroy(Folder $folder)
    {
        $user = Auth::user();
        if ($user->role->name !== 'super_admin' && $folder->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        $this->authorize('delete', $folder);

        // Soft delete rekursif untuk subtree
        $this->deleteRecursively($folder);
        return response()->json(['message' => 'Folder dipindahkan ke sampah.']);
    }

    /**
     * Daftar folder yang berada di sampah (soft-deleted)
     */
    public function trashed(Request $request)
    {
        $user = Auth::user();
        $query = Folder::onlyTrashed()
            ->with('user:id,name')
            ->withSum('files', 'ukuran_file');

        if ($user->role->name !== 'super_admin') {
            $query->where('division_id', $user->division_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Pulihkan folder dari sampah.
     * Dukung opsi new_name (rename saat restore) dan overwrite (boolean) untuk konflik nama.
     */
    public function restore(Request $request, $id)
    {
        $folder = Folder::onlyTrashed()->findOrFail($id);
        $user = Auth::user();
        if ($user->role->name !== 'super_admin' && $folder->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        $this->authorize('restore', $folder);

        $newName = $request->input('new_name');
        $overwrite = $request->boolean('overwrite');
        $nameToUse = $newName ?: $folder->name;

        $existingActive = Folder::whereNull('deleted_at')
            ->where('division_id', $folder->division_id)
            ->where('parent_folder_id', $folder->parent_folder_id)
            ->where('name', $nameToUse)
            ->first();

        if ($existingActive && !$overwrite) {
            return response()->json([
                'message' => 'Folder dengan nama yang sama sudah ada di lokasi tujuan.',
                'status' => 'conflict'
            ], 409);
        }

        if ($existingActive && $overwrite) {
            // Soft delete folder yang aktif agar tidak bentrok nama
            $this->deleteRecursively($existingActive);
        }

        // Restore rekursif
        $this->restoreRecursively($folder);

        if ($newName) {
            $folder->name = $newName;
            $folder->save();
        }

        return response()->json(['message' => 'Folder berhasil dipulihkan.']);
    }

    /**
     * Hapus permanen folder dari sampah.
     */
    public function forceDelete($id)
    {
        $folder = Folder::onlyTrashed()->findOrFail($id);
        $user = Auth::user();
        if ($user->role->name !== 'super_admin' && $folder->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        $this->authorize('forceDelete', $folder);

        // Hard delete akan mengandalkan FK cascade di DB untuk menghapus subtree
        $folder->forceDelete();

        return response()->json(['message' => 'Folder dihapus permanen.']);
    }

    /**
     * Helper: soft delete rekursif subtree folder
     */
    private function deleteRecursively(Folder $folder): void
    {
        // Hapus file aktif di folder ini
        $folder->files()->whereNull('deleted_at')->get()->each(function ($file) {
            $file->delete();
        });

        // Proses anak terlebih dahulu
        $folder->children()->whereNull('deleted_at')->get()->each(function ($child) {
            $this->deleteRecursively($child);
        });

        // Terakhir hapus folder ini
        $folder->delete();
    }

    /**
     * Helper: restore rekursif subtree folder
     */
    private function restoreRecursively(Folder $folder): void
    {
        // Pulihkan folder ini dulu
        $folder->restore();

        // Pulihkan file di folder ini
        $folder->files()->onlyTrashed()->get()->each(function ($file) {
            $file->restore();
        });

        // Pulihkan subfolder
        $folder->children()->onlyTrashed()->get()->each(function ($child) {
            $this->restoreRecursively($child);
        });
    }
}
