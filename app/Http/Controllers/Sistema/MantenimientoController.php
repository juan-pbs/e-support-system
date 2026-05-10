<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSection;
use App\Models\OrderMaintenanceHistory;
use App\Models\OrdenServicio;
use App\Support\AppSettings;
use App\Support\MaintenanceSections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MantenimientoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSystem($request);

        $sections = MaintenanceSections::ensureDefaults();

        return view('sistema.mantenimiento.index', [
            'sections' => $sections,
            'groupedSections' => $sections->groupBy(fn (MaintenanceSection $section) => str($section->key)->before('.')->toString()),
            'groupLabels' => MaintenanceSections::groupLabels(),
            'emailCotizacionesEnabled' => AppSettings::emailCotizacionesEnabled(),
            'emailOrdenesEnabled' => AppSettings::emailOrdenesEnabled(),
            'emailActasEnabled' => AppSettings::emailActasEnabled(),
            'orderMaintenanceHistories' => Schema::hasTable('order_maintenance_histories')
                ? OrderMaintenanceHistory::query()
                    ->with(['orden', 'user'])
                    ->latest()
                    ->limit(10)
                    ->get()
                : collect(),
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
            'email_cotizaciones_enabled' => ['nullable', 'boolean'],
            'email_ordenes_enabled' => ['nullable', 'boolean'],
            'email_actas_enabled' => ['nullable', 'boolean'],
        ]);

        $input = $data['sections'] ?? [];
        AppSettings::setEmailCotizacionesEnabled(
            (bool) ($data['email_cotizaciones_enabled'] ?? false),
            $request->user()->id
        );
        AppSettings::setEmailOrdenesEnabled(
            (bool) ($data['email_ordenes_enabled'] ?? false),
            $request->user()->id
        );
        AppSettings::setEmailActasEnabled(
            (bool) ($data['email_actas_enabled'] ?? false),
            $request->user()->id
        );

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

    public function reopenOrder(Request $request)
    {
        $this->authorizeSystem($request);

        $data = $request->validate([
            'orden_id' => ['required', 'integer', 'exists:orden_servicio,id_orden_servicio'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'orden_id.required' => 'Indica el ID de la orden.',
            'orden_id.exists' => 'No existe una orden con ese ID.',
        ]);

        $orden = OrdenServicio::findOrFail((int) $data['orden_id']);
        $before = $this->orderSnapshot($orden);

        if (Schema::hasColumn('orden_servicio', 'estado')) {
            $orden->estado = 'En proceso';
        }

        if (Schema::hasColumn('orden_servicio', 'acta_estado')) {
            $orden->acta_estado = 'borrador';
        }

        $orden->save();

        $this->recordOrderHistory(
            $orden,
            $request,
            'reopen_order',
            $data['reason'] ?? null,
            $before,
            $this->orderSnapshot($orden)
        );

        return redirect()
            ->route('sistema.mantenimiento')
            ->with('success', 'Orden ' . $orden->folio . ' reabierta. Ya puede editarse nuevamente.');
    }

    public function closeOrder(Request $request)
    {
        $this->authorizeSystem($request);

        $data = $request->validate([
            'orden_id' => ['required', 'integer', 'exists:orden_servicio,id_orden_servicio'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'orden_id.required' => 'Indica el ID de la orden.',
            'orden_id.exists' => 'No existe una orden con ese ID.',
        ]);

        $orden = OrdenServicio::findOrFail((int) $data['orden_id']);
        $before = $this->orderSnapshot($orden);
        $reopenSnapshot = $this->latestReopenSnapshot($orden);

        if (Schema::hasColumn('orden_servicio', 'estado')) {
            $orden->estado = $reopenSnapshot['estado'] ?? 'Completada';
        }

        if (Schema::hasColumn('orden_servicio', 'acta_estado')) {
            $orden->acta_estado = $reopenSnapshot['acta_estado'] ?? (!empty($orden->acta_pdf_path) ? 'firmada' : $orden->acta_estado);
        }

        if (Schema::hasColumn('orden_servicio', 'acta_firmada_at') && !empty($reopenSnapshot['acta_firmada_at'])) {
            $orden->acta_firmada_at = $reopenSnapshot['acta_firmada_at'];
        }

        $orden->save();

        $this->recordOrderHistory(
            $orden,
            $request,
            'close_order',
            $data['reason'] ?? null,
            $before,
            $this->orderSnapshot($orden)
        );

        return redirect()
            ->route('sistema.mantenimiento')
            ->with('success', 'Orden ' . $orden->folio . ' cerrada nuevamente conservando su acta y firmas.');
    }

    private function authorizeSystem(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isSystem') || ! $user->isSystem()) {
            abort(403);
        }
    }

    private function orderSnapshot(OrdenServicio $orden): array
    {
        return [
            'estado' => $orden->estado ?? null,
            'acta_estado' => $orden->acta_estado ?? null,
            'acta_firmada_at' => optional($orden->acta_firmada_at)->toDateTimeString(),
            'acta_pdf_path' => $orden->acta_pdf_path ?? null,
            'acta_pdf_hash' => $orden->acta_pdf_hash ?? null,
            'firma_conformidad' => $orden->firma_conformidad ?? null,
            'firma_resp_path' => $orden->firma_resp_path ?? null,
            'firma_emp_path' => $orden->firma_emp_path ?? null,
        ];
    }

    private function latestReopenSnapshot(OrdenServicio $orden): array
    {
        if (! Schema::hasTable('order_maintenance_histories')) {
            return [];
        }

        $history = OrderMaintenanceHistory::query()
            ->where('orden_id', $orden->getKey())
            ->where('action', 'reopen_order')
            ->latest()
            ->first();

        return (array) ($history?->before_snapshot ?? []);
    }

    private function recordOrderHistory(
        OrdenServicio $orden,
        Request $request,
        string $action,
        ?string $reason,
        array $before,
        array $after
    ): void {
        if (! Schema::hasTable('order_maintenance_histories')) {
            return;
        }

        OrderMaintenanceHistory::create([
            'orden_id' => $orden->getKey(),
            'user_id' => $request->user()?->id,
            'action' => $action,
            'reason' => trim((string) $reason) ?: null,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
        ]);
    }
}
