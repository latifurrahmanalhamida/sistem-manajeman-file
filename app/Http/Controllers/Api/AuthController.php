<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter; // <-- DITAMBAHKAN
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Menangani permintaan login user.
     */
    public function login(Request $request)
    {
        // 1. Validasi input
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string', // Bisa NIPP atau Email
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 2. Cek Rate Limiter
        $identifier = $request->input('identifier');
        $throttleKey = strtolower($identifier) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . ceil($seconds / 60) . ' menit.'
            ], 429); // 429 Too Many Requests
        }

        // 3. Tentukan field untuk otentikasi (email atau nipp)
        $authField = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'nipp';

        // 4. Coba temukan user berdasarkan identifier
        $user = User::where($authField, $identifier)->first();

        if (!$user) {
            // Jika user tidak ditemukan, catat percobaan dan kembalikan pesan spesifik
            RateLimiter::hit($throttleKey, 300); // Blokir selama 300 detik = 5 menit
            return response()->json([
                'message' => 'Email atau NIPP tidak terdaftar.'
            ], 401);
        }

        // 5. Jika user ditemukan, coba otentikasi dengan password
        $credentials = [
            $authField => $identifier,
            'password' => $request->input('password')
        ];

        if (!Auth::attempt($credentials)) {
            // Jika otentikasi gagal (password salah), catat percobaan
            RateLimiter::hit($throttleKey, 300); // Blokir selama 300 detik = 5 menit
            return response()->json([
                'message' => 'Password salah.'
            ], 401);
        }

        // 5. Jika berhasil, reset rate limiter dan kirim response
        RateLimiter::clear($throttleKey);

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
                'division' => $user->division ? $user->division->name : null,
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