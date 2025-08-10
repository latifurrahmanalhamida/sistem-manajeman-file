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
            'file' => 'required|file|max:10240',
            'new_name' => 'nullable|string|max:255',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $newName = $request->input('new_name');
        $overwrite = $request->boolean('overwrite');
        
        $user = Auth::user();
        $divisionId = $user->division_id;

        $fileNameToSave = $newName ?: $originalName;

        $existingFile = File::where('nama_file_asli', $fileNameToSave)
                            ->where('division_id', $divisionId)
                            ->first();

        if ($existingFile && !$overwrite) {
            return response()->json([
                'message' => 'File dengan nama "'.$fileNameToSave.'" sudah ada.',
                'status' => 'conflict'
            ], 409);
        }

        if ($existingFile && $overwrite) {
            Storage::delete($existingFile->path_penyimpanan);
            $existingFile->forceDelete();
        }

        $path = $uploadedFile->store('uploads/' . $divisionId);

        $newFile = File::create([
            'nama_file_asli' => $fileNameToSave,
            'nama_file_tersimpan' => $uploadedFile->hashName(),
            'path_penyimpanan' => $path,
            'tipe_file' => $uploadedFile->getClientMimeType(),
            'ukuran_file' => $uploadedFile->getSize(),
            'uploader_id' => $user->id,
            'division_id' => $divisionId,
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
        $file = File::findOrFail($fileId);
        $this->authorize('update', $file);
        $file->is_favorited = !$file->is_favorited;
        $file->save();
        return response()->json(['message' => 'Status favorit berhasil diubah.', 'file' => $file]);
    }

    public function restore(Request $request, $fileId)
    {
        $fileToRestore = File::onlyTrashed()->findOrFail($fileId);
        $this->authorize('restore', $fileToRestore);

        $newName = $request->input('new_name');
        $overwrite = $request->boolean('overwrite');

        $fileNameToCheck = $newName ?: $fileToRestore->nama_file_asli;

        $existingActiveFile = File::where('nama_file_asli', $fileNameToCheck)
                                  ->where('division_id', $fileToRestore->division_id)
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
        
        $fileToRestore->restore();

        if ($newName) {
            $fileToRestore->nama_file_asli = $newName;
            $fileToRestore->save();
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
        $file = File::findOrFail($fileId);
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