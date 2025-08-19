<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Division;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // <-- Impor Storage

class DivisionController extends Controller
{
    // Mengambil semua divisi beserta data tambahannya
    public function index()
    {
        // Mengambil divisi beserta jumlah user dan total ukuran file terkait
        $divisions = Division::withCount('users')
                            ->withSum('files', 'ukuran_file')
                            ->latest()
                            ->get();

        return response()->json($divisions);
    }

    // Menyimpan divisi baru dan membuat folder fisiknya
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:divisions,name',
            // Tambahkan validasi untuk Kode Divisi dan Kuota jika perlu
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $division = Division::create($request->all());

        // Membuat folder penyimpanan fisik untuk divisi baru
        Storage::makeDirectory('uploads/' . $division->id);

        return response()->json($division, 201);
    }

    // Menampilkan satu divisi
    public function show(Division $division)
    {
        // Memuat data tambahan untuk satu divisi spesifik
        $division->loadCount('users');
        $division->loadSum('files', 'ukuran_file');
        
        return response()->json($division);
    }

    // Memperbarui divisi
    public function update(Request $request, Division $division)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:divisions,name,' . $division->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $division->update($request->all());

        return response()->json($division);
    }

    // Menghapus divisi beserta folder fisiknya
    public function destroy(Division $division)
    {
        if ($division->users()->exists() || $division->files()->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus divisi karena masih memiliki data pengguna atau file terkait.'
            ], 409);
        }

        // Menghapus folder penyimpanan fisik beserta isinya
        Storage::deleteDirectory('uploads/' . $division->id);

        $division->delete();

        return response()->json(null, 204);
    }
}