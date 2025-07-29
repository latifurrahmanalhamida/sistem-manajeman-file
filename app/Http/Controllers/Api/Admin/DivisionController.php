<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Division;
use Illuminate\Support\Facades\Validator;

class DivisionController extends Controller
{
    /**
     * Terapkan middleware untuk memastikan hanya superadmin yang bisa mengakses.
     */
    public function __construct()
    {
        // 'auth:sanctum' memastikan pengguna terotentikasi.
        // Untuk 'role:superadmin', Anda perlu membuat middleware kustom sendiri
        // yang memeriksa kolom 'role' pada user.
        // $this->middleware(['auth:sanctum', 'role:superadmin']);
    }
    // Mengambil semua divisi
    public function index()
    {
        return Division::latest()->get();
    }

    // Menyimpan divisi baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:divisions,name',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $division = Division::create([
            'name' => $request->name,
        ]);

        return response()->json($division, 201);
    }

    // Menampilkan satu divisi
    public function show(Division $division)
    {
        return response()->json($division);
    }

    // Memperbarui divisi
    public function update(Request $request, Division $division)
    {
        $validator = Validator::make($request->all(), [
            // Pastikan nama unik, kecuali untuk entri saat ini
            'name' => 'required|string|max:255|unique:divisions,name,' . $division->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $division->update([
            'name' => $request->name,
        ]);

        return response()->json($division);
    }

    // Menghapus divisi
    public function destroy(Division $division)
    {
        // Tambahkan pengecekan jika divisi masih memiliki user atau file
        if ($division->users()->exists() || $division->files()->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus divisi karena masih memiliki data pengguna atau file terkait.'
            ], 409); // 409 Conflict
        }

        $division->delete();

        return response()->json(null, 204);
    }
}