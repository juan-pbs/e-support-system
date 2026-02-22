<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

use App\Traits\HasFirmaDigital;

use App\Models\Producto;
use App\Models\Inventario;
use App\Models\NumeroSerie;
use App\Models\CreditoCliente;
use App\Models\OrdenServicio;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\Cliente;
use App\Models\Firma;

use Barryvdh\DomPDF\Facade\Pdf;

class ActaConformidadController extends Controller
{
    use HasFirmaDigital;

    /* =========================================================
       ACTA DE CONFORMIDAD: Vistas y flujo principal
       ========================================================= */

    /**
     * Mostrar vista para capturar el acta de una Orden de Servicio.
     * Soporta contexto GERENTE y TÉCNICO según el nombre de la ruta.
     */
    public function actaVista(Request $request, $id)
    {
        $orden = OrdenServicio::with(['cliente'])->findOrFail($id);

        $detalles = DetalleOrdenProducto::where('id_orden_servicio', $orden->id_orden_servicio)->get();

        // Firma predeterminada del usuario (para el componente <x-firma-digital>)
        $firmaDefault = $this->readFirma();

        // Detectar si viene desde el módulo técnico según el nombre de la ruta
        $routeName = $request->route() ? $request->route()->getName() : '';
        $isTecnico = $routeName && str_starts_with($routeName, 'tecnico.');

        // Vista según el rol / contexto
        $view = $isTecnico
            ? 'vistas-tecnico.acta'
            : 'vistas-gerente.orden-servicio.acta';

        return view($view, [
            'orden'               => $orden,
            'cliente'             => $orden->cliente ?? null,
            'detalles'            => $detalles,
            'firmaDefaultEmpresa' => $firmaDefault,
        ]);
    }

    /**
     * Guardar borrador de acta (solo actualiza la orden y regresa JSON).
     * Adapta la ruta de redirección según si viene de técnico o gerente.
     */
    public function actaGuardarBorrador(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        // Si ya está firmada, no permitir ningún cambio
        if ($orden->acta_estado === 'firmada') {
            return response()->json([
                'ok'      => false,
                'message' => 'El acta de esta orden ya fue firmada y no puede modificarse.',
            ], 409);
        }

        $request->validate([
            'responsable'       => ['required', 'string', 'max:255'],
            'trabajo_realizado' => ['required', 'string'],
            'fecha'             => ['required', 'date'],
            'hora'              => ['required', 'string'],
            'conforme'          => ['required', 'in:si,no'],
        ]);

        $acta = $this->buildActaFromRequest($request, $orden);

        $orden->acta_data   = $acta;
        $orden->acta_estado = 'borrador';
        $orden->save();

        // Detectar contexto (técnico vs gerente)
        $routeName = $request->route() ? $request->route()->getName() : '';
        $isTecnico = $routeName && str_starts_with($routeName, 'tecnico.');

        // Redirección según rol
        $redirect = $isTecnico
            ? route('tecnico.detalles', ['orden' => $orden->id_orden_servicio])
            : route('seguimiento');

        return response()->json([
            'ok'       => true,
            'message'  => 'Borrador del acta guardado correctamente.',
            'redirect' => $redirect,
        ]);
    }

    /**
     * Previsualización del PDF del acta (regresa PDF en base64 dentro de JSON).
     */
    public function actaPreview(Request $request, $id)
    {
        $orden = OrdenServicio::with(['cliente'])->findOrFail($id);

        // Bloquear preview si el acta ya está firmada (solo debe verse el PDF definitivo)
        if ($orden->acta_estado === 'firmada') {
            return response()->json([
                'ok'      => false,
                'message' => 'El acta de esta orden ya está firmada. Solo puedes ver el PDF definitivo.',
            ], 409);
        }

        $request->validate([
            'responsable'       => ['required', 'string', 'max:255'],
            'trabajo_realizado' => ['required', 'string'],
            'fecha'             => ['required', 'date'],
            'hora'              => ['required', 'string'],
            'conforme'          => ['required', 'in:si,no'],
        ]);

        $acta    = $this->buildActaFromRequest($request, $orden);
        $payload = $this->buildPdfPayload($orden, $acta, true);

        $pdf    = Pdf::loadView('pdf.acta_conformidad', $payload)
                    ->setPaper('letter', 'portrait');
        $base64 = base64_encode($pdf->output());

        return response()->json([
            'ok'         => true,
            'pdf_base64' => $base64,
            'message'    => 'Previsualización generada correctamente.',
        ]);
    }

    /**
     * Confirmar el acta, marcarla como firmada y generar el PDF definitivo (CONGELADO).
     * - Guarda acta_data/estado/fecha_firma
     * - Guarda PDF físico en storage/public/actas
     * - Guarda acta_pdf_path para que el PDF NO cambie aunque cambien relaciones después
     */
    public function actaConfirmar(Request $request, $id)
    {
        $orden = OrdenServicio::with(['cliente'])->findOrFail($id);

        // Si ya está firmada, no permitir reconfirmar / sobrescribir
        if ($orden->acta_estado === 'firmada') {
            return response()->json([
                'ok'      => false,
                'message' => 'El acta de esta orden ya fue firmada y no puede modificarse.',
            ], 409);
        }

        $request->validate([
            'responsable'       => ['required', 'string', 'max:255'],
            'trabajo_realizado' => ['required', 'string'],
            'fecha'             => ['required', 'date'],
            'hora'              => ['required', 'string'],
            'conforme'          => ['required', 'in:si,no'],
            'firma_responsable' => ['required', 'string'],
        ], [
            'firma_responsable.required' => 'La firma del responsable que recibe es obligatoria para confirmar el acta.',
        ]);

        $acta = $this->buildActaFromRequest($request, $orden);

        // Guardar firma predeterminada del usuario si se marcó desde el componente
        $this->saveFirmaDefaultFromRequest($request);

        // Cerrar OS si está conforme y se marcó la casilla
        if (($acta['cerrar_os'] ?? false) && ($acta['conforme'] ?? 'si') === 'si') {
            $table = $orden->getTable();
            if (Schema::hasColumn($table, 'estatus')) {
                $orden->estatus = 'Completada';
            } elseif (Schema::hasColumn($table, 'estado')) {
                $orden->estado = 'Completada';
            }
        }

        // Guardar datos definitivos del acta
        $orden->acta_data   = $acta;
        $orden->acta_estado = 'firmada';

        $payload = $this->buildPdfPayload($orden, $acta, false);

        $pdf    = Pdf::loadView('pdf.acta_conformidad', $payload)
                    ->setPaper('letter', 'portrait');
        $binary = $pdf->output();

        // Guardar PDF en storage/public/actas
        $folder   = 'actas';
        $filename = $folder . '/acta_conformidad_' . $orden->id_orden_servicio . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($filename, $binary);

        // ✅ CONGELAR PDF: guardar ruta + fecha firma + hash
        $orden->acta_pdf_path   = $filename;               // <- TU CAMPO
        $orden->acta_firmada_at = now();                   // <- TU CAMPO
        $orden->acta_pdf_hash   = hash('sha256', $binary); // <- TU CAMPO

        $orden->save();

        // Detectar contexto (técnico vs gerente)
        $routeName = $request->route() ? $request->route()->getName() : '';
        $isTecnico = $routeName && str_starts_with($routeName, 'tecnico.');

        // URL del PDF vía ruta
        $pdfUrl = $isTecnico
            ? route('tecnico.ordenes.acta.pdf', ['id' => $orden->id_orden_servicio])
            : route('ordenes.acta.pdf', ['id' => $orden->id_orden_servicio]);

        // Redirección tras confirmar
        $redirect = $isTecnico
            ? route('tecnico.servicios')
            : route('seguimiento');

        return response()->json([
            'ok'       => true,
            'message'  => 'Acta confirmada y PDF generado correctamente.',
            'pdf_url'  => $pdfUrl,
            'redirect' => $redirect,
        ]);
    }

    /**
     * Ver/descargar el PDF del acta.
     * ✅ Si está firmada, regresa el PDF definitivo guardado en acta_pdf_path (NO se regenera).
     * Si no existe, hace fallback a regenerar desde acta_data.
     */
    public function actaPdf($id)
    {
        $orden = OrdenServicio::with(['cliente'])->findOrFail($id);

        // ✅ Si ya está firmada, servir el PDF congelado
        if ($orden->acta_estado === 'firmada' && !empty($orden->acta_pdf_path)) {
            $disk = Storage::disk('public');

            if ($disk->exists($orden->acta_pdf_path)) {
                return $disk->response(
                    $orden->acta_pdf_path,
                    "acta_conformidad_{$orden->id_orden_servicio}.pdf",
                    ['Content-Type' => 'application/pdf']
                );
            }
        }

        // Fallback (por si faltara el archivo): regenerar desde acta_data
        $acta = is_array($orden->acta_data)
            ? $orden->acta_data
            : (json_decode($orden->acta_data ?? '[]', true) ?: []);

        $payload = $this->buildPdfPayload($orden, $acta, false);

        $pdf = Pdf::loadView('pdf.acta_conformidad', $payload)
                ->setPaper('letter', 'portrait');

        return $pdf->stream("acta_conformidad_{$orden->id_orden_servicio}.pdf");
    }

    /**
     * Construye el array de datos del acta a partir del Request.
     */
    private function buildActaFromRequest(Request $request, OrdenServicio $orden): array
    {
        return [
            'responsable'       => $request->input('responsable'),
            'puesto'            => $request->input('puesto'),
            'fecha'             => $request->input('fecha'),
            'hora'              => $request->input('hora'),
            'trabajo_realizado' => $request->input('trabajo_realizado'),
            'conforme'          => $request->input('conforme', 'si'),
            'observaciones'     => $request->input('observaciones'),
            'cerrar_os'         => $request->boolean('cerrar_os'),

            // Firmas
            'firma_responsable' => $request->input('firma_responsable'),

            // Firma empresa (puede venir del componente)
            'firma_empresa'     => $request->input('firma_empresa'),      // base64 de la firma empresa
            'firma_emp_nombre'  => $request->input('firma_emp_nombre'),
            'firma_emp_puesto'  => $request->input('firma_emp_puesto'),
            'firma_emp_empresa' => $request->input('firma_emp_empresa'),
        ];
    }

    /**
     * Arma el payload que consume la vista PDF (orden, acta, detalles, extras, totales, firmas, etc.).
     */
    private function buildPdfPayload(OrdenServicio $orden, array $acta, bool $preview): array
    {
        $cliente = $orden->cliente ?? null;

        // Detalles de productos de la orden
        $detalles = DetalleOrdenProducto::where('id_orden_servicio', $orden->id_orden_servicio)->get();

        $subtotalProductos = 0.0;
        foreach ($detalles as $d) {
            $cant    = (float)($d->cantidad ?? 0);
            $pu      = (float)($d->precio_unitario ?? $d->precio ?? 0);
            $importe = (float)($d->total ?? ($cant * $pu));
            $subtotalProductos += $importe;
        }

        // Materiales / gastos extra (si existe el modelo)
        $extras      = [];
        $totalExtras = 0.0;
        if (class_exists(\App\Models\OrdenMaterialExtra::class)) {
            $extras = \App\Models\OrdenMaterialExtra::where('id_orden_servicio', $orden->id_orden_servicio)->get();
            foreach ($extras as $e) {
                $cant    = (float)($e->cantidad ?? 0);
                $pu      = (float)($e->precio_unitario ?? $e->precio ?? 0);
                $importe = (float)($e->total ?? ($cant * $pu));
                $totalExtras += $importe;
            }
        }

        // Otros costos (si tu tabla tiene este campo; si no, queda en 0)
        $otrosCostos  = (float)($orden->costo_operativo ?? 0);
        $totalGeneral = $subtotalProductos + $totalExtras + $otrosCostos;

        // Firmas
        $firmaClienteSrc = $this->normalizeDataUri($acta['firma_responsable'] ?? null);

        $firmaEmpresaSrc = null;
        if (!empty($acta['firma_empresa'])) {
            $firmaEmpresaSrc = $this->normalizeDataUri($acta['firma_empresa']);
        } else {
            // Tomar firma predeterminada guardada para el usuario (trait HasFirmaDigital)
            $firmaEmpDefault = $this->readFirma();
            $firmaEmpresaSrc = $firmaEmpDefault['image'] ?? null;

            $acta['firma_emp_nombre']  = $acta['firma_emp_nombre']  ?? ($firmaEmpDefault['nombre'] ?? null);
            $acta['firma_emp_puesto']  = $acta['firma_emp_puesto']  ?? ($firmaEmpDefault['puesto'] ?? null);
            $acta['firma_emp_empresa'] = $acta['firma_emp_empresa'] ?? ($firmaEmpDefault['empresa'] ?? null);
        }

        // Técnicos (solo si el modelo define la relación)
        $tecnicos = [];
        if (method_exists($orden, 'tecnicos')) {
            try {
                $tecnicos = $orden->tecnicos()->get();
            } catch (\Throwable $e) {
                $tecnicos = [];
            }
        }

        return [
            'orden'              => $orden,
            'cliente'            => $cliente,
            'acta'               => $acta,
            'tecnicos'           => $tecnicos,
            'detalles'           => $detalles,
            'extras'             => $extras,
            'subtotal_productos' => $subtotalProductos,
            'total_extras'       => $totalExtras,
            'otros_costos'       => $otrosCostos,
            'total_general'      => $totalGeneral,
            'firma_cliente_src'  => $firmaClienteSrc,
            'firma_empresa_src'  => $firmaEmpresaSrc,
            'cotizacion'         => null,
            'draft'              => $preview,
        ];
    }

    /* ===================== Helpers de firma ===================== */

    /**
     * Normaliza un posible data URI/base64 para que Dompdf no truene.
     */
    private function normalizeDataUri(?string $value): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        $v = trim($value);

        if (stripos($v, 'data:image/') === 0) {
            $parts = explode('base64,', $v, 2);
            if (count($parts) === 2) {
                $v = $parts[0] . 'base64,' . str_replace(' ', '+', $parts[1]);
            }
            return $v;
        }

        return 'data:image/png;base64,' . str_replace(' ', '+', $v);
    }

    /* =========================================================
       APIs de productos / tipo de cambio / stock / crédito
       ========================================================= */

    /* ===================== Productos: búsqueda / autocomplete ===================== */

    public function apiBuscarProductos(Request $request)
    {
        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['items' => []]);

        $items = Producto::query()
            ->activos()
            ->where(function ($qb) use ($q) {
                $qb->where('codigo_producto', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('numero_parte', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['codigo_producto', 'nombre', 'descripcion', 'numero_parte'])
            ->map(fn($p) => [
                'codigo_producto' => $p->codigo_producto,
                'nombre'          => $p->nombre,
                'descripcion'     => $p->descripcion,
                'numero_parte'    => $p->numero_parte,
                'precio_unitario' => 0,
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function autocomplete(Request $request)
    {
        $q = trim($request->get('q', ''));
        if ($q === '') return response()->json([]);

        $items = Producto::query()
            ->activos()
            ->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhere('numero_parte', 'like', "%{$q}%")
                    ->orWhere('codigo_producto', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get(['codigo_producto', 'nombre', 'descripcion', 'numero_parte']);

        return response()->json($items);
    }

    public function apiCrearProductoRapido(Request $request)
    {
        $data = $request->validate([
            'codigo'       => ['nullable', 'integer'],
            'nombre'       => ['required', 'string', 'max:255'],
            'descripcion'  => ['nullable', 'string'],
            'numero_parte' => ['nullable', 'string', 'max:255'],
            'unidad'       => ['nullable', 'string', 'max:50'],
        ]);

        $producto = new Producto();
        if (!empty($data['codigo'])) $producto->codigo_producto = $data['codigo'];
        $producto->nombre       = $data['nombre'];
        $producto->descripcion  = $data['descripcion'] ?? null;
        $producto->numero_parte = $data['numero_parte'] ?? null;
        $producto->unidad       = $data['unidad'] ?? 'pz';
        $producto->activo       = true;
        $producto->stock_seguridad      = 0;
        $producto->stock_total          = 0;
        $producto->stock_paquetes       = 0;
        $producto->stock_piezas_sueltas = 0;
        $producto->save();

        return response()->json([
            'ok'   => true,
            'item' => [
                'codigo_producto' => $producto->codigo_producto,
                'nombre'          => $producto->nombre,
                'descripcion'     => $producto->descripcion,
                'numero_parte'    => $producto->numero_parte,
                'precio_unitario' => 0,
            ]
        ], 201);
    }

    public function storeRapido(Request $request)
    {
        $data = $request->validate([
            'nombre'        => ['required', 'string', 'max:255'],
            'descripcion'   => ['nullable', 'string'],
            'numero_parte'  => ['nullable', 'string', 'max:255'],
            'unidad'        => ['nullable', 'string', 'max:50'],
            'activo'        => ['nullable', 'boolean'],
            'categoria'     => ['nullable', 'string', 'max:100'],
        ]);

        $producto = Producto::create([
            'nombre'                => $data['nombre'],
            'descripcion'           => $data['descripcion'] ?? null,
            'numero_parte'          => $data['numero_parte'] ?? null,
            'unidad'                => $data['unidad'] ?? 'pz',
            'stock_seguridad'       => 0,
            'stock_total'           => 0,
            'stock_paquetes'        => 0,
            'stock_piezas_sueltas'  => 0,
            'categoria'             => $data['categoria'] ?? 'Otra',
            'activo'                => $data['activo'] ?? true,
        ]);

        return response()->json($producto->only(['codigo_producto', 'nombre', 'descripcion', 'numero_parte']));
    }

    /* ===================== Tipo de cambio ===================== */

    public function apiTipoCambio(Request $request)
    {
        $base = strtoupper($request->query('base', 'MXN'));
        $to   = strtoupper($request->query('to', 'USD'));
        if ($base === $to) return response()->json(['rate' => 1.0]);
        if ($base === 'USD' && $to === 'MXN') $rate = 17.2000;
        elseif ($base === 'MXN' && $to === 'USD') $rate = 0.0581;
        else $rate = 1.0;
        return response()->json(['rate' => $rate]);
    }

    /* ===================== Stock / Series ===================== */

    public function apiProductoStock(Request $request)
    {
        $codigo = (int)$request->query('codigo');
        if ($codigo <= 0) {
            return response()->json([
                'ok'               => true,
                'stock'            => 0,
                'stock_max'        => 0,
                'disponible'       => 0,
                'stock_disponible' => 0,
                'has_serial'       => false,
            ]);
        }

        $entradas = Inventario::where('codigo_producto', $codigo)->get();
        if ($entradas->isEmpty()) {
            return response()->json([
                'ok'               => true,
                'stock'            => 0,
                'stock_max'        => 0,
                'disponible'       => 0,
                'stock_disponible' => 0,
                'has_serial'       => false,
            ]);
        }

        $invIds = $entradas->pluck('id');

        $stockSerial = NumeroSerie::whereIn('inventario_id', $invIds)->count();

        $stockNoSerial = 0;
        foreach ($entradas as $e) {
            if ($this->isSerialType($e->tipo_control)) continue;

            $ppp     = max((int)($e->piezas_por_paquete ?? 0), 0);
            $packs   = max((int)($e->paquetes_restantes ?? 0), 0);
            $sueltas = max((int)($e->piezas_sueltas ?? 0), 0);

            $stockNoSerial += ($ppp > 0 ? ($packs * $ppp) : 0) + $sueltas;
        }

        $stock = max((int)($stockSerial + $stockNoSerial), 0);

        return response()->json([
            'ok'               => true,
            'stock'            => $stock,
            'stock_max'        => $stock,
            'disponible'       => $stock,
            'stock_disponible' => $stock,
            'has_serial'       => (bool)$this->productHasSerial($codigo),
        ]);
    }

    public function apiPeekSeries(Request $request)
    {
        $codigo = (int)($request->query('codigo') ?? $request->query('codigo_producto'));
        if ($codigo <= 0) return response()->json(['ok' => true, 'series' => []]);

        $invIds = Inventario::where('codigo_producto', $codigo)->pluck('id');
        if ($invIds->isEmpty()) return response()->json(['ok' => true, 'series' => []]);

        $seriales = NumeroSerie::whereIn('inventario_id', $invIds)
            ->orderBy('id')
            ->pluck('numero_serie')
            ->toArray();

        return response()->json(['ok' => true, 'series' => $seriales]);
    }

    private function isSerialType(?string $tipo): bool
    {
        $t = strtolower(trim((string)$tipo));
        return $t === 'serie'
            || $t === 'serial'
            || $t === 'ns'
            || $t === 'n/s'
            || str_contains($t, 'serie')
            || str_contains($t, 'serial');
    }

    private function productHasSerial(int $codigoProducto): bool
    {
        $entradas = Inventario::where('codigo_producto', $codigoProducto)->get();
        if ($entradas->isEmpty()) return false;
        $invIds = $entradas->pluck('id');
        return $entradas->contains(fn($e) => $this->isSerialType($e->tipo_control))
            || NumeroSerie::whereIn('inventario_id', $invIds)->exists();
    }

    /* ===================== Crédito del cliente ===================== */

    public function apiCreditoCliente(Request $request)
    {
        $clave = (int)($request->query('cliente') ?? $request->query('id_cliente') ?? 0);
        if ($clave <= 0) {
            return response()->json([
                'ok' => true, 'exists' => false,
                'monto_maximo' => 0, 'monto_usado' => 0, 'disponible' => 0,
                'dias_credito' => null, 'estatus' => null,
                'expired' => false, 'fecha_limite' => null, 'dias_restantes' => null,
            ]);
        }

        $cred = CreditoCliente::where('clave_cliente', $clave)->first();
        if (!$cred) {
            return response()->json([
                'ok' => true, 'exists' => false,
                'monto_maximo' => 0, 'monto_usado' => 0, 'disponible' => 0,
                'dias_credito' => null, 'estatus' => null,
                'expired' => false, 'fecha_limite' => null, 'dias_restantes' => null,
            ]);
        }

        $venc    = $this->checkCreditoVencido($cred);
        $estatus = $venc['expired'] ? 'vencido' : ($cred->estatus ?? 'activo');

        return response()->json([
            'ok'             => true,
            'exists'         => true,
            'monto_maximo'   => (float)$cred->monto_maximo,
            'monto_usado'    => (float)$cred->monto_usado,
            'disponible'     => max((float)$cred->monto_maximo - (float)$cred->monto_usado, 0),
            'dias_credito'   => $cred->dias_credito,
            'estatus'        => $estatus,
            'expired'        => (bool)$venc['expired'],
            'fecha_limite'   => $venc['fecha_limite'],
            'dias_restantes' => $venc['dias_restantes'],
        ]);
    }

    private function checkCreditoVencido(?CreditoCliente $cred): array
    {
        if (!$cred) return ['expired' => false, 'dias_restantes' => null, 'fecha_limite' => null];

        try {
            $hoy         = now()->startOfDay();
            $asignacion  = $cred->fecha_asignacion ? \Carbon\Carbon::parse($cred->fecha_asignacion) : null;
            $dias        = (int)($cred->dias_credito ?? 0);
            $fechaLimite = $asignacion ? (clone $asignacion)->addDays($dias > 0 ? $dias : 0) : null;

            $expired = $fechaLimite ? $hoy->greaterThan($fechaLimite) : (strtolower((string)$cred->estatus) === 'vencido');
            $rest    = $fechaLimite ? $hoy->diffInDays($fechaLimite, false) : null;

            return [
                'expired'        => (bool)$expired,
                'dias_restantes' => $rest,
                'fecha_limite'   => $fechaLimite?->format('Y-m-d'),
            ];
        } catch (\Throwable $e) {
            return ['expired' => false, 'dias_restantes' => null, 'fecha_limite' => null];
        }
    }
}
