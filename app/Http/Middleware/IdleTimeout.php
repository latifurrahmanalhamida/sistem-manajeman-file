<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IdleTimeout
{
    /**
     * Handle an incoming request.
     * If the authenticated user's current access token has been idle for
     * more than the configured threshold, invalidate it and return 401.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();

            if ($token) {
                $lastUsedAt = $token->last_used_at;

                // Jika token pernah digunakan, cek durasi idle
                if ($lastUsedAt) {
                    $lastUsed = Carbon::parse($lastUsedAt);

                    // Batas idle 15 menit
                    $idleLimitMinutes = 15;

                    if (now()->diffInMinutes($lastUsed) >= $idleLimitMinutes) {
                        // Hapus token yang idle terlalu lama dan paksa logout
                        $token->delete();

                        return response()->json([
                            'message' => 'Session expired due to inactivity.'
                        ], 401);
                    }
                }
            }
        }

        return $next($request);
    }
}
