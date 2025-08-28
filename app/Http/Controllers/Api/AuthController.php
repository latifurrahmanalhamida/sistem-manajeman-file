<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

// --- TAMBAHKAN IMPORT INI ---
use Illuminate\Auth\Events\Logout;
// -----------------------------

class AuthController extends Controller
{
    /**
     * Menangani permintaan login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $loginInput = $request->input('login');
        $user = User::where('email', $loginInput)
                    ->orWhere('nipp', $loginInput)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Email atau NIPP tidak terdaftar.'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password yang Anda masukkan salah.'], 401);
        }

        Auth::login($user);
        return $this->sendLoginSuccessResponse($user);
    }

    /**
     * Helper function untuk mengirim response saat login berhasil.
     */
    protected function sendLoginSuccessResponse(User $user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;
        // Kita tidak perlu query lagi karena data user sudah lengkap
        $user->load(['role', 'division']);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Mengambil data user yang sedang terotentikasi.
     * FUNGSI INI TELAH DIPERBAIKI.
     */
    public function user(Request $request)
    {
        // Ambil user yang sedang login dan sertakan relasinya
        $user = $request->user()->load(['role', 'division']);
        
        // Kirimkan kembali dengan format yang sama seperti saat login
        return response()->json($user);
    }

    /**
     * Menangani permintaan logout user.
     * --- FUNGSI INI TELAH DIPERBAIKI ---
     */
    public function logout(Request $request)
    {
        // Panggil event Logout secara manual sebelum token dihapus
        event(new Logout('sanctum', $request->user()));

        // Hapus token otentikasi
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logout berhasil.']);
    }
}