<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $parentId = $request->input('parent_id') ?: null;

        $query = Folder::with('user:id,name')->where('parent_folder_id', $parentId);

        if ($user->role->name === 'super_admin') {
            if ($request->has('division_id')) {
                $query->where('division_id', $request->input('division_id'));
            }
        } else {
            $query->where('division_id', $user->division_id);
        }

        $folders = $query->latest()->get();
        $folders->each(function ($folder) {
            // Memanggil helper method di bawah, tidak ada yang diubah dari logika asli Anda
            $folder->files_sum_ukuran_file = $this->calculateRecursiveSize($folder);
        });

        return response()->json($folders);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $this->authorize('create', Folder::class);
        $parentId = $request->input('parent_id') ?: null;
        
        $divisionId = $user->role->name === 'super_admin'
            ? $request->input('division_id')
            : $user->division_id;

        $validated = $request->validate([ // <-- Array ke-1 (untuk Aturan) dimulai di sini
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('folders', 'name')->where(function ($q) use ($divisionId, $parentId) {
                    return $q->where('division_id', $divisionId)
                            ->where('parent_folder_id', $parentId)
                            ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
            'division_id' => ($user->role->name === 'super_admin') ? ['required', 'integer', 'exists:divisions,id'] : [],
        ], [ // <-- Array ke-1 ditutup, dan Array ke-2 (untuk Pesan Error) dimulai di sini
            'name.unique' => 'Nama folder ini sudah ada di lokasi ini.'
        ]);

        $folder = Folder::create([
            'name' => $validated['name'],
            'division_id' => $divisionId,
            'user_id' => $user->id,
            'parent_folder_id' => $parentId,
        ]);

        // [FIX] Membuat direktori fisik di storage
        Storage::disk('local')->makeDirectory($folder->getFullPath());

        return response()->json([
            'message' => 'Folder berhasil dibuat.',
            'data' => $folder,
        ], 201);
    }

    public function show(Folder $folder)
    {
        $this->authorize('view', $folder);
        $folder->load([
            'children:id,name,user_id,updated_at,parent_folder_id,division_id', 'children.user:id,name',
            'files:id,nama_file_asli,uploader_id,updated_at,ukuran_file,folder_id,division_id', 'files.uploader:id,name'
        ]);
        return response()->json([
            'folder' => $folder,
            'breadcrumbs' => $folder->getBreadcrumbs()->values(),
        ]);
    }

public function update(Request $request, Folder $folder)
{
    try {
        $this->authorize('update', $folder);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('folders', 'name')
                    ->ignore($folder->id)
                    ->where(function ($q) use ($folder) {
                        return $q->where('division_id', $folder->division_id)
                                 ->where('parent_folder_id', $folder->parent_folder_id)
                                 ->whereNull('deleted_at');
                    }),
            ],
        ], [
            'name.unique' => 'Nama folder ini sudah ada di lokasi ini.'
        ]);
        
        $oldPath = $folder->getFullPath();

        DB::beginTransaction();
        
        $folder->name = $validated['name'];
        // Observer 'updating' akan berjalan di sini jika sukses
        $folder->save(); 

        $newPath = $folder->getFullPath();

        if ($oldPath !== $newPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->move($oldPath, $newPath);

            $filesToUpdate = File::where('path_penyimpanan', 'like', $oldPath . '%')->get();
            foreach ($filesToUpdate as $file) {
                $newFilePath = str_replace($oldPath, $newPath, $file->path_penyimpanan);
                $file->update(['path_penyimpanan' => $newFilePath]);
            }
        }

        DB::commit();

        return response()->json(['message' => 'Folder berhasil diperbarui.', 'data' => $folder]);

    } catch (ValidationException $e) {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Gagal Mengubah Nama Folder',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => ['info' => "Upaya mengubah nama '{$folder->name}' menjadi '{$request->input('name')}' gagal karena nama sudah ada."],
            'status'      => 'Gagal',
        ]);
        return response()->json(['errors' => $e->errors()], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Gagal memperbarui folder.', 'error' => $e->getMessage()], 500);
    }
}

    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);
        // Soft delete tidak menghapus folder fisik, agar bisa direstore
        $this->deleteRecursively($folder);
        return response()->json(['message' => 'Folder dipindahkan ke sampah.']);
    }

    public function trashed(Request $request)
    {
        $user = Auth::user();
        $query = Folder::onlyTrashed()->with('user:id,name');

        if ($user->role->name === 'super_admin') {
            if ($request->has('division_id')) {
                $query->where('division_id', $request->input('division_id'));
            }
        } else {
            $query->where('division_id', $user->division_id);
        }
        $folders = $query->latest()->get();
        $folders->each(function ($folder) {
            $folder->files_sum_ukuran_file = $this->calculateRecursiveSize($folder);
        });
        return response()->json($folders);
    }
    
    public function restore($id)
    {
        $folder = Folder::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $folder);
        
        // Cek apakah parent folder-nya masih ada (tidak terhapus)
        if ($folder->parent_folder_id && Folder::find($folder->parent_folder_id) === null) {
             return response()->json(['message' => 'Tidak dapat memulihkan karena folder induknya tidak ada.'], 422);
        }
        
        $this->restoreRecursively($folder);
        return response()->json(['message' => 'Folder berhasil dipulihkan.']);
    }

    public function forceDelete($id)
    {
        $folder = Folder::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $folder);

        $path = $folder->getFullPath();

        $folder->forceDelete();
        
        // [FIX] Hapus direktori fisik secara permanen
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->deleteDirectory($path);
        }

        return response()->json(['message' => 'Folder dihapus permanen.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods (Tidak Ada yang Diubah dari Kode Asli Anda)
    |--------------------------------------------------------------------------
    */

    private function deleteRecursively(Folder $folder): void
    {
        foreach ($folder->children as $child) {
            $this->deleteRecursively($child);
        }
        $folder->files()->delete();
        $folder->delete();
    }
    
    private function restoreRecursively(Folder $folder): void
    {
        foreach ($folder->children()->onlyTrashed()->get() as $child) {
            $this->restoreRecursively($child);
        }
        $folder->files()->onlyTrashed()->restore();
        $folder->restore();
    }

    private function getDescendantFolderIds(Folder $folder)
    {
        $descendantIds = collect();
        foreach ($folder->children as $child) {
            $descendantIds->push($child->id);
            $descendantIds = $descendantIds->merge($this->getDescendantFolderIds($child));
        }
        return $descendantIds;
    }

    private function calculateRecursiveSize(Folder $folder)
    {
        $allFolderIds = $this->getDescendantFolderIds($folder);
        $allFolderIds->push($folder->id);
        return \App\Models\File::whereIn('folder_id', $allFolderIds)->sum('ukuran_file');
    }

 public function getDivisionLogs(Request $request)
{
    $user = Auth::user();

    if ($user->role->name !== 'admin_devisi' && $user->role->name !== 'super_admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $query = ActivityLog::with('user:id,name');

    if ($user->role->name === 'admin_devisi') {
        $query->where('division_id', $user->division_id);
    } 
    elseif ($user->role->name === 'super_admin' && $request->has('division_id')) {
        $query->where('division_id', $request->input('division_id'));
    }

    $logs = $query->latest()->paginate(20);

    // [FIX] Gunakan 'through()' untuk mengubah setiap item di dalam paginator
    $transformedLogs = $logs->through(function ($log) {
        return [
            'id' => $log->id,
            'causer' => $log->user,
            'description' => $log->action,
            'properties' => ['details' => $log->details],
            'created_at' => $log->created_at->toIso8601String(),
            'updated_at' => $log->updated_at->toIso8601String(),
        ];
    });

    return response()->json($transformedLogs);
}
}