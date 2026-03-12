<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->guard('api')->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado. Se requiere rol de administrador.'], 403);
        }

        return $next($request);
    }
}
