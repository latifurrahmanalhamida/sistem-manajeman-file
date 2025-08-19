<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\File;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user berdasarkan hak akses.
     */
    public function index()
    {
        $admin = Auth::user();
        $query = User::with('role:id,name', 'division:id,name');

        if ($admin->role->name === 'admin_devisi') {
            $query->where('division_id', $admin->division_id);
        }

        $users = $query->get()->map(function ($user) {
            $storageUsed = File::where('uploader_id', $user->id)->sum('ukuran_file');
            $user->penyimpanan_digunakan = round($storageUsed / 1024 / 1024, 2) . ' MB';
            return $user;
        });

        return response()->json($users);
    }

    /**
     * Menyimpan user baru.
     */
    public function store(Request $request)
    {
        $admin = Auth::user();
        
        if (!in_array($admin->role->name, ['super_admin', 'admin_devisi'])) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk membuat user.'], 403);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'nipp' => 'nullable|string|unique:users,nipp',
            'username' => 'nullable|string|unique:users,username',
        ];

        if ($admin->role->name === 'super_admin') {
            $rules['role_id'] = 'required|exists:roles,id';
            $rules['division_id'] = 'required|exists:divisions,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $roleId = null;
        $divisionId = null;

        if ($admin->role->name === 'super_admin') {
            $roleId = $request->role_id;
            $divisionId = $request->division_id;
        } else { // Admin Devisi
            $userRole = Role::where('name', 'user_devisi')->first();
            $roleId = $userRole->id;
            $divisionId = $admin->division_id;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nipp' => $request->nipp,
            'username' => $request->username,
            'role_id' => $roleId,
            'division_id' => $divisionId,
        ]);

        return response()->json(['message' => 'User berhasil dibuat.', 'user' => $user->load('role', 'division')], 201);
    }

    /**
     * Menampilkan satu data user spesifik.
     */
    public function show(User $user)
    {
        $admin = Auth::user();
        if ($admin->role->name === 'admin_devisi' && $admin->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        return response()->json($user);
    }

    /**
     * Memperbarui data user.
     * --- FUNGSI INI TELAH DIPERBAIKI ---
     */
public function update(Request $request, User $user)
{
    $admin = Auth::user();
    if ($admin->role->name === 'admin_devisi' && $admin->division_id !== $user->division_id) {
        return response()->json(['message' => 'Akses ditolak.'], 403);
    }

    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        'nipp' => 'nullable|string|unique:users,nipp,' . $user->id,
        'username' => 'nullable|string|unique:users,username,' . $user->id,
    ];
    
    // Tambahkan validasi password HANYA JIKA diisi
    if ($request->filled('password')) {
        $rules['password'] = 'required|string|min:8';
    }

    if ($admin->role->name === 'super_admin') {
        $rules['role_id'] = 'required|exists:roles,id';
        $rules['division_id'] = 'required|exists:divisions,id';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }
    
    $dataToUpdate = $request->only(['name', 'email', 'nipp', 'username']);

    // Jika ada password baru, hash dan tambahkan ke data update
    if ($request->filled('password')) {
        $dataToUpdate['password'] = Hash::make($request->password);
    }

    if ($admin->role->name === 'super_admin') {
        $dataToUpdate['role_id'] = $request->role_id;
        $dataToUpdate['division_id'] = $request->division_id;
    }

    $user->update($dataToUpdate);

    return response()->json(['message' => 'User berhasil diperbarui.', 'user' => $user->load('role', 'division')]);
}

    /**
     * Menghapus user.
     */
    public function destroy(User $user)
    {
        $admin = Auth::user();

        if ($admin->role->name === 'admin_devisi' && $admin->division_id !== $user->division_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        
        if ($admin->id === $user->id) {
            return response()->json(['message' => 'Anda tidak bisa menghapus diri sendiri.'], 403);
        }

        File::where('uploader_id', $user->id)->update(['uploader_id' => $admin->id]);

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }

    /**
     * Mengembalikan user yang sudah di-soft delete.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }
        
        $user->restore();

        return response()->json(['message' => 'User berhasil dikembalikan.', 'user' => $user]);
    }

    /**
     * Menghapus user secara permanen.
     */
    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }
        
        $user->forceDelete();

        return response()->json(['message' => 'User berhasil dihapus permanen.']);
    }
    
    public function trashed()
    {
        $admin = Auth::user();
        
        $query = User::onlyTrashed()->with('role:id,name', 'division:id,name');

        if ($admin->role->name === 'admin_devisi') {
            $query->where('division_id', $admin->division_id);
        }

        $trashedUsers = $query->get();

        return response()->json($trashedUsers);
    }
}