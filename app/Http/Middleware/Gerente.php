<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Gerente
{
    /**
     * Permite acceso a usuarios con rol 'gerente' o 'admin'.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->route('login');
        }

        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole(['admin', 'gerente'])) {
                return $next($request);
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'No autorizado para este módulo.'], 403)
                : redirect()->route('dashboard')
                    ->with('error', 'No tienes acceso a esa sección con este usuario.');
        }

        $rol = $user->puesto ?? $user->role ?? $user->rol ?? $user->tipo ?? null;
        $rol = is_string($rol) ? strtolower(trim($rol)) : '';

        if (in_array($rol, ['gerente', 'admin'], true)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'No autorizado para este módulo.'], 403)
            : redirect()->route('dashboard')
                ->with('error', 'No tienes acceso a esa sección con este usuario.');
    }
}
