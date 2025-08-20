<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class FolderController extends Controller
{
    /**
     * Menampilkan daftar folder berdasarkan parent folder.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $parentId = $request->query('parent_id');

        $query = Folder::with('user:id,name')
                       ->withSum('files', 'ukuran_file') // <-- INI YANG MENGHITUNG UKURAN
                       ->where('parent_id', $parentId);

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
                                 ->where('parent_id', $request->parent_id);
                }),
            ],
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $folder = Folder::create([
            'name' => $validated['name'],
            'division_id' => $user->division_id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

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
                                 ->where('parent_id', $folder->parent_id);
                })->ignore($folder->id),
            ],
        ]);

        $folder->update(['name' => $validated['name']]);
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

        $folder->delete();
        return response()->json(null, 204);
    }
}