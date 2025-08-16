<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // <-- Tambahkan ini
use Illuminate\Support\Facades\Validator;
use App\Models\User; // <-- Pastikan model User di-import

class AuthController extends Controller
{
    /**
     * Menangani permintaan login user dengan pesan error spesifik.
     */
    public function login(Request $request)
    {
        // 1. Validasi input dasar
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $loginInput = $request->input('login');
        $password = $request->input('password');

        // 2. Cari user berdasarkan email ATAU nipp
        $user = User::where('email', $loginInput)
                    ->orWhere('nipp', $loginInput)
                    ->first();

        // 3. Jika user tidak ditemukan, kirim error spesifik
        if (!$user) {
            return response()->json([
                'message' => 'Email atau NIPP tidak terdaftar.'
            ], 401);
        }

        // 4. Jika user ditemukan, cek password-nya
        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Password yang Anda masukkan salah.'
            ], 401);
        }

        // 5. Jika semua cocok, login berhasil
        Auth::login($user);
        return $this->sendLoginSuccessResponse();
    }

    /**
     * Helper function untuk mengirim response saat login berhasil.
     */
    protected function sendLoginSuccessResponse()
    {
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name,
                'division' => $user->division ? $user->division->name : null
            ]
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Menangani permintaan logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }
}