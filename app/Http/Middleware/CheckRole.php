<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role  (Ini adalah role yg kita inginkan, misal 'admin')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        // 1. Cek jika user sudah login (pasti sudah, krn ada auth:sanctum)
        // 2. Cek jika user punya role yang diminta
        if ($request->user() && $request->user()->roles()->where('name', $role)->exists()) {
            
            // Jika punya, lanjutkan request
            return $next($request);
        }

        // Jika tidak punya, kirim error 403 Forbidden
        return response()->json([
            'message' => 'You do not have permission to access this resource.'
        ], 403);
    }
}