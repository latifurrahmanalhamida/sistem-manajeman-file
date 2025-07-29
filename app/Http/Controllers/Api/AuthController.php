<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Menangani permintaan login user.
     */
    public function login(Request $request)
    {
        // Validasi input dari user
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Coba untuk melakukan otentikasi
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau Password salah.'
            ], 401); // 401 artinya Unauthorized
        }

        // Jika berhasil, ambil data user
        $user = $request->user();

        // Buat token API (menggunakan Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // Kirim response berisi token dan data user
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name, // Mengambil nama peran dari relasi
                'division' => $user->division ? $user->division->name : null // Mengambil nama divisi jika ada
            ]
        ]);
    }

    /**
     * Mendapatkan data user yang sedang login.
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Menangani permintaan logout user.
     */
    public function logout(Request $request)
    {
        // Hapus token otentikasi saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }
}