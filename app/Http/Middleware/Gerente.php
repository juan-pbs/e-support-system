<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Gerente
{
    /**
     * Permite acceso a usuarios con rol 'gerente' o 'admin'.
     * Soporta tanto atributo simple (e.g. $user->puesto/role/rol/tipo)
     * como Spatie (hasAnyRole), si lo tuvieras instalado.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        // ✅ Si usas Spatie/laravel-permission (opcional):
        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole(['admin', 'gerente'])) {
                return $next($request);
            }
            abort(403);
        }

        // ✅ Atributo simple en tu modelo User (ajusta el campo si hace falta)
        $rol = $user->puesto ?? $user->role ?? $user->rol ?? $user->tipo ?? null;
        $rol = is_string($rol) ? strtolower($rol) : '';

        if (!in_array($rol, ['gerente', 'admin'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
