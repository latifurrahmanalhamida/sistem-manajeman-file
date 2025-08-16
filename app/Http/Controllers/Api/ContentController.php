<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Folder;
use App\Models\File;

class ContentController extends Controller
{
    /**
     * Mengambil folder dan file untuk divisi pengguna yang diautentikasi.
     * Mendukung navigasi folder dengan `folder_id`.
     */
    public function getContents(Request $request)
    {
        $request->validate([
            'folder_id' => 'nullable|integer|exists:folders,id',
        ]);

        $user = Auth::user();
        
        // PERBAIKAN 1: Ambil folder_id dengan nilai default null
        $parentFolderId = $request->input('folder_id', null);

        // Ambil folder berdasarkan parent_folder_id
        $folders = Folder::where('division_id', $user->division_id)
            ->where('parent_folder_id', $parentFolderId)
            ->with(['user:id,name'])
            ->latest() // Urutkan dari yang terbaru
            ->get();

        // Ambil file berdasarkan folder_id
        $files = File::where('division_id', $user->division_id)
            ->where('folder_id', $parentFolderId)
            ->with(['uploader:id,name'])
            ->latest() // Urutkan dari yang terbaru
            ->get();

        // PERBAIKAN 2: Kembalikan dalam format objek dengan dua key
        return response()->json([
            'folders' => $folders,
            'files' => $files,
        ]);
    }
}