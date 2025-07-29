<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles (ini akan menangkap semua peran yang dikirim dari route, misal: 'super_admin', 'admin_devisi')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Ambil user yang sedang login
        $user = $request->user();

        // Cek apakah peran user ada di dalam daftar peran yang diizinkan
        if ($user && in_array($user->role->name, $roles)) {
            // Jika diizinkan, lanjutkan permintaan ke Controller
            return $next($request);
        }

        // Jika tidak diizinkan, tolak akses dengan pesan error
        return response()->json([
            'message' => 'Akses ditolak. Anda tidak memiliki izin yang cukup.'
        ], 403); // 403 artinya Forbidden (Dilarang)
    }
}