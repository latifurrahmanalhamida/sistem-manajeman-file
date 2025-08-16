<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    // ... method lain yang mungkin sudah ada di sini ...

    /**
     * Menyimpan folder baru ke database.
     */
    public function store(Request $request)
    {
        // 1. Validasi input dari form
        $validatedData = $request->validate([
            'nama_folder' => 'required|string|max:255',
            // Tambahkan validasi lain jika perlu
        ]);

        // 2. Logika untuk menyimpan data
        // Contoh:
        // $folder = Folder::create($validatedData);

        // 3. Beri respons sukses
        return response()->json([
            'message' => 'Folder berhasil dibuat!',
            // 'data' => $folder 
        ], 201); // 201 artinya Created
    }

    /**
     * Permanently delete the specified folder from storage.
     */
    public function forceDelete($id)
    {
        $folder = Folder::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $folder);

        // Note: This assumes that related files and subfolders are handled
        // by database foreign key constraints (e.g., ON DELETE CASCADE).
        $folder->forceDelete();

        return response()->json(null, 204);
    }
}