<?php

namespace App\Http\Middleware;

use App\Models\MaintenanceSection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckSectionMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('maintenance_sections')) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $sections = Cache::remember('maintenance_sections.enabled', 60, function () {
            return MaintenanceSection::query()
                ->where('enabled', true)
                ->get(['key', 'name', 'paths', 'message']);
        });

        foreach ($sections as $section) {
            foreach ((array) $section->paths as $pattern) {
                if ($request->is($pattern)) {
                    return response()->view('errors.section-maintenance', [
                        'section' => $section,
                    ], 503);
                }
            }
        }

        return $next($request);
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('sistema/mantenimiento*') || $request->is('sistema/usuarios-conectados*')) {
            return true;
        }

        if ($request->is('login', 'logout', 'forgot-password', 'reset-password*', 'up')) {
            return true;
        }

        $user = $request->user();

        return $user && method_exists($user, 'isSystem') && $user->isSystem();
    }
}
