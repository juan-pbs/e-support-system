<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSection;
use App\Support\MaintenanceSections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MantenimientoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSystem($request);

        return view('sistema.mantenimiento.index', [
            'sections' => MaintenanceSections::ensureDefaults(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSystem($request);

        MaintenanceSections::ensureDefaults();

        $data = $request->validate([
            'sections' => ['array'],
            'sections.*.enabled' => ['nullable', 'boolean'],
            'sections.*.message' => ['nullable', 'string', 'max:255'],
        ]);

        $input = $data['sections'] ?? [];

        MaintenanceSection::query()->get()->each(function (MaintenanceSection $section) use ($input, $request) {
            $payload = $input[$section->key] ?? [];

            $section->update([
                'enabled' => (bool) ($payload['enabled'] ?? false),
                'message' => $payload['message'] ?? $section->message,
                'updated_by' => $request->user()->id,
            ]);
        });

        Cache::forget('maintenance_sections.enabled');

        return redirect()
            ->route('sistema.mantenimiento')
            ->with('success', 'Configuracion de mantenimiento actualizada.');
    }

    private function authorizeSystem(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isSystem') || ! $user->isSystem()) {
            abort(403);
        }
    }
}
