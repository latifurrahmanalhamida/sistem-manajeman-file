<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MoveTokenFromQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Periksa apakah token ada di query parameter dan tidak ada di header
        if ($request->has('token') && !$request->header('Authorization')) {
            // Ambil token dari query
            $token = $request->input('token');

            // Pindahkan token ke header Authorization dengan format Bearer
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
