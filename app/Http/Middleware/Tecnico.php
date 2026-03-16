<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Symfony\Component\HttpFoundation\Response;

class Tecnico
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!FacadesAuth::check()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->route('login');
        }

        $user = FacadesAuth::user();
        $rol = $user->puesto ?? $user->role ?? $user->rol ?? $user->tipo ?? null;
        $rol = is_string($rol) ? strtolower(trim($rol)) : '';

        if ($rol === 'tecnico') {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'No autorizado para este módulo.'], 403)
            : redirect()->route('dashboard')
                ->with('error', 'No tienes acceso a esa sección con este usuario.');
    }
}
