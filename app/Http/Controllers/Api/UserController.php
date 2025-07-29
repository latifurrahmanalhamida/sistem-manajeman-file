<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user berdasarkan hak akses.
     */
    public function index()
    {
        $user = Auth::user();
        $query = User::with('role:id,name', 'division:id,name');

        if ($user->role->name === 'super_admin') {
            // Super Admin bisa melihat semua user
        } 
        else if ($user->role->name === 'admin_devisi') {
            // Admin Devisi hanya bisa melihat user di divisinya
            $query->where('division_id', $user->division_id);
        } 
        else {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $users = $query->get();
        return response()->json($users);
    }

    /**
     * Membuat user baru.
     */
    public function store(Request $request)
    {
        $admin = Auth::user();

        if (!in_array($admin->role->name, ['super_admin', 'admin_devisi'])) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk membuat user.'], 403);
        }

        // Validasi dasar
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ];

        // Validasi tambahan jika yang membuat adalah Super Admin
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
            // Super Admin bebas menentukan peran dan divisi dari input
            $roleId = $request->role_id;
            $divisionId = $request->division_id;
        } else { // Jika Admin Devisi
            // Admin Devisi hanya bisa membuat user_devisi di divisinya sendiri
            $userRole = Role::where('name', 'user_devisi')->first();
            $roleId = $userRole->id;
            $divisionId = $admin->division_id;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $roleId,
            'division_id' => $divisionId,
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user' => $user->load('role', 'division')
        ], 201);
    }
}