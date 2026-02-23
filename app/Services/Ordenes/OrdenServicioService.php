<?php

declare(strict_types=1);

namespace App\Services\Ordenes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

use App\Models\User;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\OrdenServicio;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\Producto;
use App\Models\Inventario;
use App\Models\NumeroSerie;
use App\Models\SerieReserva;
use App\Models\CreditoCliente;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\HasFirmaDigital;

class OrdenServicioService
{
    use HasFirmaDigital;

    /** Cache simple para Schema::hasColumn('orden_servicio', ...) */
    protected array $ordenColsCache = [];

    public function ordenHasColumn(string $col): bool
    {
        if (array_key_exists($col, $this->ordenColsCache)) {
            return (bool) $this->ordenColsCache[$col];
        }

        try {
            $this->ordenColsCache[$col] = Schema::hasColumn('orden_servicio', $col);
        } catch (\Throwable $e) {
            $this->ordenColsCache[$col] = false;
        }

        return (bool) $this->ordenColsCache[$col];
    }

    /** ✅ Wrapper público para evitar "Call to protected method readFirma()" */
    public function getFirma(): array
    {
        return $this->readFirma();
    }

    public function ensurePrivateOrdenDirs(): void
    {
        foreach (['private/ordenes/actas', 'private/ordenes/firmas'] as $dir) {
            if (!Storage::exists($dir)) {
                Storage::makeDirectory($dir);
            }
        }
    }

    /* ==================== Form create / catálogos ==================== */

    public function commonFormData(): array
    {
        $clientes = Cliente::orderBy('nombre')->get([
            'clave_cliente',
            'nombre',
            'nombre_empresa',
            'correo_electronico',
            'telefono',
            'ubicacion',
            'direccion_fiscal',
        ]);

        $tecnicos = User::where('puesto', 'tecnico')
            ->orderBy('name')
            ->get(['id', 'name']);

        $prioridades = ['Baja', 'Media', 'Alta', 'Urgente'];
        $tiposOrden  = ['compra', 'servicio_simple', 'servicio_proyecto'];

        $productos = Producto::activos()
            ->with(['inventario' => fn($q) => $q->latest('fecha_entrada')])
            ->orderBy('nombre')
            ->get();

        foreach ($productos as $p) {
            $p->imagen_url = $p->imagen
                ? (str_starts_with($p->imagen, 'http') ? $p->imagen : asset($p->imagen))
                : asset('images/imagen.png');

            try {
                $stock = $this->calculateAvailableForProduct((int) $p->codigo_producto);
            } catch (\Throwable $e) {
                $stock = 0;
            }

            $p->stock_disponible = $stock;
            $p->stock            = $stock;
            $p->disponible       = $stock;
            $p->stock_max        = $stock;
            $p->has_serial       = $this->productHasSerial((int) $p->codigo_producto);
        }

        return compact('clientes', 'tecnicos', 'prioridades', 'tiposOrden', 'productos');
    }

    /* ==================== Anticipo helpers ==================== */

    public function computeAnticipoFromData(array $data, float $totalOrden): array
    {
        $modo = (string) ($data['anticipo_modo'] ?? '');

        $totalOrden = max((float) $totalOrden, 0.0);
        $anticipo = 0.0;

        $pctIn = $data['anticipo_porcentaje'] ?? null;
        if ($pctIn !== null && $pctIn !== '') {
            $modo = 'porcentaje';
            $pct = (float) $pctIn;
            if ($pct < 0) $pct = 0;
            if ($pct > 100) $pct = 100;
            $anticipo = $totalOrden * ($pct / 100);
        } else {
            $modo = 'monto';
            $monto = $data['anticipo'] ?? ($data['anticipo_monto'] ?? 0);
            $monto = (float) $monto;
            if ($monto < 0) $monto = 0;
            $anticipo = $monto;
        }

        if ($anticipo > $totalOrden) $anticipo = $totalOrden;
        if ($anticipo < 0) $anticipo = 0;

        $anticipo = round($anticipo, 2);
        $saldo    = round(max($totalOrden - $anticipo, 0), 2);

        $pctCalc = $totalOrden > 0 ? round(($anticipo / $totalOrden) * 100, 2) : 0;

        return [
            'modo'     => $modo,
            'anticipo' => $anticipo,
            'saldo'    => $saldo,
            'pct'      => $pctCalc,
        ];
    }

    public function applyAnticipoToOrden(OrdenServicio $orden, array $data, array $totales): array
    {
        $totalOrden = (float) ($totales['total'] ?? 0);

        $calc = $this->computeAnticipoFromData($data, $totalOrden);

        $anticipo = (float) $calc['anticipo'];
        $saldo    = (float) $calc['saldo'];

        $moneda = strtoupper((string) ($orden->moneda ?? 'MXN'));
        $tc     = (float) ($orden->tasa_cambio ?? 0);

        $totalMXN     = $totalOrden;
        $anticipoMXN  = $anticipo;
        $saldoMXN     = $saldo;

        if ($moneda === 'USD') {
            if ($tc > 0) {
                $totalMXN    = round($totalOrden * $tc, 2);
                $anticipoMXN = round($anticipo * $tc, 2);
                $saldoMXN    = round($saldo * $tc, 2);
            } else {
                $totalMXN = 0;
                $anticipoMXN = 0;
                $saldoMXN = 0;
            }
        }

        if (isset($data['anticipo_mxn']) && $data['anticipo_mxn'] !== '' && $data['anticipo_mxn'] !== null) {
            $anticipoMXN = round(max((float)$data['anticipo_mxn'], 0), 2);
            if ($anticipoMXN > $totalMXN) $anticipoMXN = $totalMXN;

            if ($moneda === 'USD') {
                $anticipo = ($tc > 0) ? round($anticipoMXN / $tc, 2) : 0;
            } else {
                $anticipo = $anticipoMXN;
            }

            $saldo    = round(max($totalOrden - $anticipo, 0), 2);
            $saldoMXN = round(max($totalMXN - $anticipoMXN, 0), 2);
        }

        $pct = null;
        if (isset($data['anticipo_porcentaje']) && $data['anticipo_porcentaje'] !== '' && $data['anticipo_porcentaje'] !== null) {
            $pct = (float) $data['anticipo_porcentaje'];
            if ($pct < 0) $pct = 0;
            if ($pct > 100) $pct = 100;
        } else {
            $pct = ($totalMXN > 0) ? round(($anticipoMXN / $totalMXN) * 100, 2) : 0;
        }

        $orden->anticipo_mxn = $anticipoMXN;
        $orden->anticipo_porcentaje = $pct;

        return [
            'total'        => round($totalOrden, 2),
            'anticipo'     => round($anticipo, 2),
            'saldo'        => round($saldo, 2),
            'total_mxn'    => round($totalMXN, 2),
            'anticipo_mxn' => round($anticipoMXN, 2),
            'saldo_mxn'    => round($saldoMXN, 2),
            'pct'          => round($pct, 2),
            'modo'         => $calc['modo'],
        ];
    }

    /* ===================== Validación ===================== */

    public function validateOrden(Request $request, bool $fromCotizacion = false): array
    {
        $raw = $request->input('productos');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['productos' => array_values($decoded)]);
            } else {
                $request->merge(['productos' => []]);
            }
        }

        $rules = [
            'id_cliente'            => ['required', 'integer', 'exists:cliente,clave_cliente'],
            'servicio'              => ['nullable', 'string'],
            'tipo_orden'            => ['required', 'in:compra,servicio_simple,servicio_proyecto'],
            'prioridad'             => ['required', 'in:Baja,Media,Alta,Urgente'],
            'estado'                => ['nullable', 'string'],

            'id_tecnico'            => ['nullable', 'integer', 'exists:users,id'],
            'tecnicos_ids'          => ['nullable', 'array'],
            'tecnicos_ids.*'        => ['integer', 'exists:users,id'],

            'tipo_pago'             => ['nullable', 'string'],

            'precio'                => ['nullable', 'numeric'],
            'costo_operativo'       => ['nullable', 'numeric'],
            'descripcion'           => ['nullable', 'string'],
            'descripcion_servicio'  => ['nullable', 'string'],
            'condiciones_generales' => ['nullable', 'string'],

            'moneda'      => ['nullable', 'in:MXN,USD'],
            'tasa_cambio' => ['nullable', 'numeric'],

            'fecha_programada'   => ['nullable', 'date'],
            'fecha_compromiso'   => ['nullable', 'date'],
            'fecha_orden'        => ['nullable', 'date'],
            'fecha_finalizacion' => ['nullable', 'date'],

            'anticipo_mxn'        => ['nullable', 'numeric', 'min:0'],
            'anticipo_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'anticipo_modo'       => ['nullable', 'in:monto,porcentaje'],
            'anticipo_monto'      => ['nullable', 'numeric', 'min:0'],
            'anticipo'            => ['nullable', 'numeric', 'min:0'],

            'productos'                   => ['nullable', 'array'],
            'productos.*.codigo_producto' => ['nullable'],
            'productos.*.descripcion'     => ['nullable', 'string'],
            'productos.*.nombre_producto' => ['nullable', 'string', 'max:255'],
            'productos.*.cantidad'        => ['nullable', 'numeric'],
            'productos.*.precio'          => ['nullable', 'numeric'],
            'productos.*.ns_asignados'     => ['nullable', 'array'],
            'productos.*.ns_asignados.*'   => ['nullable', 'string'],

            'firma_base64'          => ['nullable', 'string'],
            'firma_svg'             => ['nullable', 'string'],
            'firma_nombre'          => ['nullable', 'string', 'max:255'],
            'firma_puesto'          => ['nullable', 'string', 'max:255'],
            'firma_empresa'         => ['nullable', 'string', 'max:255'],
            'firma_guardar_default' => ['nullable'],

            'firma_autorizacion'        => ['nullable', 'string'],
            'firma_autorizacion_base64' => ['nullable', 'string'],

            // ✅ Token para reservar N/S durante la captura
            'serial_token'              => ['nullable', 'string', 'max:80'],
        ];

        if ($fromCotizacion) {
            $rules['cotizacion_id']     = ['required', 'integer', 'exists:cotizaciones,id_cotizacion'];
            $rules['estado_cotizacion'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        if (!empty($data['fecha_programada']) && empty($data['fecha_orden'])) {
            $data['fecha_orden'] = Carbon::today()->toDateString();
        }

        if (!empty($data['fecha_compromiso']) && empty($data['fecha_finalizacion'])) {
            $data['fecha_finalizacion'] = Carbon::parse($data['fecha_compromiso'])->toDateString();
        }

        if (empty($data['fecha_orden'])) {
            $data['fecha_orden'] = Carbon::today()->toDateString();
        }

        if (!isset($data['productos']) || !is_array($data['productos'])) {
            $data['productos'] = [];
        }

        return $data;
    }

    public function fillOrden(OrdenServicio $orden, array $data): void
    {
        $orden->id_cliente = $data['id_cliente'];

        $orden->servicio   = $data['servicio'] ?? $orden->servicio;
        $orden->tipo_orden = $data['tipo_orden'];
        $orden->prioridad  = $data['prioridad'];
        $orden->estado     = $data['estado'] ?? ($orden->estado ?? 'Pendiente');

        $orden->id_tecnico = $data['id_tecnico'] ?? ($data['tecnicos_ids'][0] ?? null);

        $orden->tipo_pago = $data['tipo_pago'] ?? null;

        $orden->precio          = (float) ($data['precio'] ?? 0);
        $orden->costo_operativo = (float) ($data['costo_operativo'] ?? 0);

        $orden->moneda      = $data['moneda'] ?? ($orden->moneda ?? 'MXN');
        $orden->tasa_cambio = $data['tasa_cambio'] ?? ($orden->tasa_cambio ?? null);

        $orden->descripcion = $data['descripcion'] ?? null;

        $orden->descripcion_servicio = $data['descripcion_servicio']
            ?? ($orden->descripcion_servicio ?? null);

        $orden->condiciones_generales =
            (isset($data['condiciones_generales']) && $data['condiciones_generales'] !== '')
            ? $data['condiciones_generales']
            : null;

        $orden->fecha_orden = !empty($data['fecha_orden'])
            ? Carbon::parse($data['fecha_orden'])->toDateString()
            : ($orden->fecha_orden ?? Carbon::today()->toDateString());

        $orden->fecha_finalizacion = !empty($data['fecha_finalizacion'])
            ? Carbon::parse($data['fecha_finalizacion'])->toDateString()
            : $orden->fecha_finalizacion;

        if (isset($data['anticipo_porcentaje']) && $data['anticipo_porcentaje'] !== '' && $data['anticipo_porcentaje'] !== null) {
            $orden->anticipo_porcentaje = (float) $data['anticipo_porcentaje'];
        }
        if (isset($data['anticipo_mxn']) && $data['anticipo_mxn'] !== '' && $data['anticipo_mxn'] !== null) {
            $orden->anticipo_mxn = (float) $data['anticipo_mxn'];
        }
    }

    public function normalizeDataUriImage(?string $value): ?string
    {
        if (!$value) return null;

        $value = trim($value);
        if ($value === '') return null;

        if (str_starts_with($value, 'data:image/')) return $value;

        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
            $clean = preg_replace('/\s+/', '', $value);
            return 'data:image/png;base64,' . $clean;
        }

        return null;
    }

    public function handleUploads(OrdenServicio $orden, Request $request): void
    {
        $firmaRaw = $request->input('firma_base64');
        if (!$firmaRaw) {
            $firmaRaw = $request->input('firma_autorizacion')
                ?: $request->input('firma_autorizacion_base64');
        }

        $firmaPref      = $this->getFirma();
        $firmaParaOrden = $firmaRaw ?: ($firmaPref['image'] ?? null);
        $firmaDataUri   = $this->normalizeDataUriImage($firmaParaOrden);

        if ($firmaDataUri) {
            $this->ensurePrivateOrdenDirs();

            $b64    = preg_replace('#^data:image/[^;]+;base64,#i', '', $firmaDataUri);
            $binary = base64_decode($b64, true);

            if ($binary !== false) {
                $filename = 'private/ordenes/firmas/firma_orden_' . uniqid() . '.png';
                Storage::put($filename, $binary);

                $orden->firma_conformidad = $filename;
            }
        }

        $this->saveFirmaDefaultFromRequest($request);
    }

    /* ===================== PDF DEFINITIVO ===================== */

    public function responsePublicPdf(string $path, string $filename, bool $download = false)
    {
        $disk = Storage::disk('public');

        $bin  = $disk->get($path);
        $mime = $disk->mimeType($path) ?: 'application/pdf';

        $disposition = ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"';

        return response($bin, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $disposition,
        ]);
    }

    public function deleteArchivoPdfIfExists(OrdenServicio $orden): void
    {
        if (!empty($orden->archivo_pdf) && Storage::disk('public')->exists($orden->archivo_pdf)) {
            Storage::disk('public')->delete($orden->archivo_pdf);
        }
    }

    public function generarYGuardarPdfOrden(int $ordenId): string
    {
        $orden = OrdenServicio::with(['cliente', 'tecnicos'])->findOrFail($ordenId);

        try {
            $orden->recalcularTotalAdicionalMxn();
            $orden->refresh();
        } catch (\Throwable $e) {
            // noop
        }

        $detalles = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->get();

        $productos = $detalles->map(function ($p) {
            $desc = $p->descripcion ?? $p->detalle ?? null;
            $qty  = (float) ($p->cantidad ?? 0);
            $pu   = (float) ($p->precio_unitario ?? 0);

            return (object) [
                'nombre_producto' => $p->nombre_producto ?? ($desc ?? 'Producto'),
                'descripcion'     => $desc,
                'detalle'         => null,
                'cantidad'        => $qty,
                'precio_unitario' => $pu,
                'total'           => max(($qty * $pu), 0),
                'ns_asignados'    => $this->extractSerialsFromText($desc ?? ''),
            ];
        });

        $lineasForTotals = $detalles->map(function ($p) {
            return [
                'cantidad'     => (float)($p->cantidad ?? 0),
                'precio'       => (float)($p->precio_unitario ?? 0),
                'ns_asignados'  => [],
            ];
        })->toArray();

        $adicional = 0.0;
        try {
            $adicional = (float) $orden->total_adicional;
        } catch (\Throwable $e) {
            $adicional = 0.0;
        }

        $totales = $this->calculateTotals(
            $lineasForTotals,
            (float)($orden->precio ?? 0),
            (float)($orden->costo_operativo ?? 0),
            $adicional
        );

        $orden->impuestos = (float)($totales['iva'] ?? 0);
        $orden->save();

        $cliente = $orden->cliente;

        $firma = $this->getFirma();

        $firma_base64 = null;
        $pathFirma    = $orden->firma_conformidad;

        if (!empty($pathFirma) && Storage::exists($pathFirma)) {
            try {
                $bin          = Storage::get($pathFirma);
                $mime         = Storage::mimeType($pathFirma) ?: 'image/png';
                $firma_base64 = 'data:' . $mime . ';base64,' . base64_encode($bin);
            } catch (\Throwable $e) {
                // noop
            }
        }

        config([
            'dompdf.options.isRemoteEnabled'      => true,
            'dompdf.options.isHtml5ParserEnabled' => true,
        ]);

        $pdf = Pdf::loadView('pdf.orden_servicio', [
            'orden'        => $orden,
            'cliente'      => $cliente,
            'productos'    => $productos,
            'firma_base64' => $firma_base64,
            'firma'        => $firma,
        ])->setPaper('letter')->setOptions([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
        ]);

        $this->deleteArchivoPdfIfExists($orden);

        $path = 'ordenes/pdf/orden_servicio_' . $orden->id_orden_servicio . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $orden->archivo_pdf = $path;
        $orden->save();

        return $path;
    }

    /* ===================== Helpers de líneas / totales ===================== */

    public function unitPriceFrom(array $item): float
    {
        foreach (['precio', 'precio_unitario', 'precio_unit', 'unit_price', 'unitPrice', 'price'] as $k) {
            if (isset($item[$k]) && $item[$k] !== '' && $item[$k] !== null) {
                return (float) $item[$k];
            }
        }
        return 0.0;
    }

    public function quantityFrom(array $item): float
    {
        if (!empty($item['ns_asignados']) && is_array($item['ns_asignados'])) {
            $n = count(array_filter($item['ns_asignados'], fn($s) => is_string($s) && $s !== ''));
            if ($n > 0) return (float) $n;
        }

        foreach (['cantidad', 'qty', 'quantity'] as $k) {
            if (isset($item[$k]) && $item[$k] !== '' && $item[$k] !== null) {
                return (float) $item[$k];
            }
        }
        return 0.0;
    }

    public function insertDetallesOrden(OrdenServicio $orden, array $productos, string $moneda): void
    {
        $hasMoneda = Schema::hasColumn('detalle_orden_producto', 'moneda');

        foreach ($productos as $item) {
            $producto = null;
            if (!empty($item['codigo_producto'])) {
                $producto = Producto::find($item['codigo_producto']);
            }

            $nombreProducto = $item['nombre_producto']
                ?? ($producto->nombre ?? null)
                ?? ($item['descripcion'] ?? 'Producto');

            $descripcion = $item['descripcion'] ?? ($producto->descripcion ?? null);
            $cantidad    = $this->quantityFrom($item);
            $precio      = $this->unitPriceFrom($item);

            $serials = array_values(array_filter((array) ($item['ns_asignados'] ?? [])));

            // Adjuntar seriales a la descripción (solo para bitácora) sin romper el layout
            if (!empty($serials)) {
                $descHasNs = is_string($descripcion) && stripos($descripcion, 'NS:') !== false;
                if (!$descHasNs) {
                    $descripcion = trim(($descripcion ? $descripcion . "\n" : '') . 'NS: ' . implode(', ', $serials));
                }
            }

            $totalLinea = round(($cantidad * $precio), 2);
            if ($totalLinea < 0) $totalLinea = 0;

            $insert = [
                'id_orden_servicio' => $orden->getKey(),
                'codigo_producto'   => $item['codigo_producto'] ?? null,
                'nombre_producto'   => $nombreProducto,
                'descripcion'       => $descripcion,
                'cantidad'          => $cantidad,
                'precio_unitario'   => $precio,
                'impuesto'          => 0,
                'total'             => $totalLinea,
            ];

            if ($hasMoneda) {
                $insert['moneda'] = $moneda;
            }

            $detalle = DetalleOrdenProducto::create($insert);

            // ✅ Registrar seriales usados en tabla detalle_orden_producto_series
            if (!empty($serials) && $detalle && $detalle->id_orden_producto) {
                $rows = array_map(function ($ns) use ($detalle) {
                    return [
                        'id_orden_producto' => $detalle->id_orden_producto,
                        'numero_serie'      => $ns,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }, $serials);

                DetalleOrdenProductoSerie::insert($rows);
            }
        }
    }

    public function recalcularYGuardarImpuestos(
        OrdenServicio $orden,
        array $productos,
        $costoServicio = 0,
        $costoOperativo = 0,
        $adicional = 0
    ): void {
        $materialBruto = 0.0;
        foreach ($productos as $p) {
            $qty   = $this->quantityFrom($p);
            $price = $this->unitPriceFrom($p);
            $materialBruto += ($qty * $price);
        }

        $baseGravable = (float) $materialBruto + (float) $costoServicio + (float) $adicional;
        $iva          = round($baseGravable * 0.16, 2);

        $orden->impuestos = $iva;
        $orden->save();
    }

    public function calculateTotals(
        array $productos,
        $costoServicio = 0,
        $costoOperativo = 0,
        $adicional = 0
    ): array {
        $material = 0.0;
        foreach ($productos as $p) {
            $qty   = $this->quantityFrom($p);
            $price = $this->unitPriceFrom($p);
            $material += ($qty * $price);
        }

        $base     = (float) $material + (float) $costoServicio + (float) $adicional;
        $iva      = round($base * 0.16, 2);
        $subtotal = $base + (float) $costoOperativo;
        $total    = round($subtotal + $iva, 2);

        return [
            'material'  => round($material, 2),
            'adicional' => round((float)$adicional, 2),
            'base'      => round($base, 2),
            'iva'       => $iva,
            'subtotal'  => round($subtotal, 2),
            'total'     => $total,
        ];
    }

    /* ===================== INVENTARIO / SERIES ===================== */

    /**
     * Limpia reservas expiradas para que los N/S vuelvan a estar disponibles.
     */
    public function cleanupExpiredSerieReservas(): void
    {
        try {
            SerieReserva::query()
                ->where('estado', 'reservado')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();
        } catch (\Throwable $e) {
            // noop
        }
    }

    /**
     * ✅ Seriales NO disponibles para asignación:
     * - estado = asignado (siempre)
     * - estado = reservado (activo) PERO de otro token
     */
    protected function serialesNoDisponibles(int $codigoProducto, ?string $token = null): array
    {
        $this->cleanupExpiredSerieReservas();

        try {
            $q = SerieReserva::query()
                ->where('codigo_producto', $codigoProducto)
                ->where(function ($qq) use ($token) {
                    $qq->where('estado', 'asignado')
                        ->orWhere(function ($q2) use ($token) {
                            $q2->where('estado', 'reservado')
                                ->where(function ($q3) {
                                    $q3->whereNull('expires_at')->orWhere('expires_at', '>', now());
                                });
                            if ($token) {
                                $q2->where('token', '!=', $token);
                            }
                        });
                });

            return $q->pluck('numero_serie')->filter()->values()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Reserva (bloquea) N/S para que NO aparezcan disponibles en otras capturas.
     * - Usa "token" del formulario para identificar al dueño.
     * - Si algún N/S ya está reservado por otra captura o asignado, se regresa en "taken".
     */
    public function reserveSeries(int $codigoProducto, array $seriales, string $token, ?int $userId = null, int $ttlMinutes = 30): array
    {
        $this->cleanupExpiredSerieReservas();

        $codigoProducto = (int) $codigoProducto;
        $token = trim((string) $token);
        $ttlMinutes = max((int) $ttlMinutes, 5);
        $expiresAt = now()->addMinutes($ttlMinutes);

        $seriales = array_values(array_unique(
            array_filter(array_map(fn($s) => trim((string)$s), (array)$seriales), fn($s) => $s !== '')
        ));

        if ($codigoProducto <= 0 || $token === '' || empty($seriales)) {
            return ['ok' => false, 'reserved' => [], 'taken' => $seriales, 'expires_at' => $expiresAt->toDateTimeString()];
        }

        $reserved = [];
        $taken    = [];

        DB::beginTransaction();
        try {
            foreach ($seriales as $ns) {
                // Validar que el N/S exista realmente para ese producto (inventario o tabla numeros_serie)
                $existsInInv = Inventario::where('codigo_producto', $codigoProducto)
                    ->where('numero_serie', $ns)
                    ->exists();

                $existsInTable = false;
                if (!$existsInInv) {
                    $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
                    $existsInTable = $invIds->isNotEmpty()
                        ? NumeroSerie::query()
                        ->where('numero_serie', $ns)
                        ->whereIn('inventario_id', $invIds)
                        ->exists()
                        : false;
                }

                if (!$existsInInv && !$existsInTable) {
                    $taken[] = $ns;
                    continue;
                }

                $row = SerieReserva::query()
                    ->where('codigo_producto', $codigoProducto)
                    ->where('numero_serie', $ns)
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    // Si es del mismo token, refrescamos expiración.
                    if ($row->token === $token && $row->estado === 'reservado') {
                        $row->expires_at = $expiresAt;
                        $row->user_id    = $userId;
                        $row->save();
                        $reserved[] = $ns;
                        continue;
                    }

                    // Si está asignado o reservado por otro token, lo marcamos como tomado.
                    $isActiveReserve = ($row->estado === 'reservado') && (empty($row->expires_at) || $row->expires_at->gt(now()));
                    if ($row->estado === 'asignado' || $isActiveReserve) {
                        $taken[] = $ns;
                        continue;
                    }

                    // Reserva expirada / liberada: la reutilizamos
                    $row->token       = $token;
                    $row->user_id     = $userId;
                    $row->estado      = 'reservado';
                    $row->reserved_at = now();
                    $row->expires_at  = $expiresAt;
                    $row->source_type = null;
                    $row->source_id   = null;
                    $row->assigned_at = null;
                    $row->save();
                    $reserved[] = $ns;
                    continue;
                }

                SerieReserva::create([
                    'codigo_producto' => $codigoProducto,
                    'numero_serie'    => $ns,
                    'token'           => $token,
                    'user_id'         => $userId,
                    'estado'          => 'reservado',
                    'reserved_at'     => now(),
                    'expires_at'      => $expiresAt,
                ]);
                $reserved[] = $ns;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['ok' => false, 'reserved' => [], 'taken' => $seriales, 'expires_at' => $expiresAt->toDateTimeString()];
        }

        return [
            'ok'         => empty($taken),
            'reserved'   => $reserved,
            'taken'      => $taken,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    /**
     * Libera reservas del token (total o parcial).
     */
    public function releaseSeries(string $token, ?array $seriales = null, ?int $codigoProducto = null): int
    {
        $this->cleanupExpiredSerieReservas();

        $token = trim((string) $token);
        if ($token === '') return 0;

        $q = SerieReserva::query()->where('token', $token)->where('estado', 'reservado');

        if ($codigoProducto !== null) {
            $q->where('codigo_producto', (int)$codigoProducto);
        }

        if (is_array($seriales) && count($seriales)) {
            $seriales = array_values(array_unique(
                array_filter(array_map(fn($s) => trim((string)$s), $seriales), fn($s) => $s !== '')
            ));
            if (!empty($seriales)) {
                $q->whereIn('numero_serie', $seriales);
            }
        }

        try {
            return (int) $q->delete();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Marca reservas como asignadas (opcional: para auditoría).
     * Nota: llámalo DESPUÉS de guardar la orden/cotización.
     */
    public function finalizeSeries(string $token, string $sourceType, int $sourceId): void
    {
        $token = trim((string)$token);
        if ($token === '') return;

        try {
            SerieReserva::query()
                ->where('token', $token)
                ->where('estado', 'reservado')
                ->update([
                    'estado'       => 'asignado',
                    'source_type'  => $sourceType,
                    'source_id'    => $sourceId,
                    'assigned_at'  => now(),
                    'expires_at'   => null,
                    'updated_at'   => now(),
                ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    /**
     * ✅ Robustez: al borrar una orden, liberar N/S asignados a esa orden.
     * (Esto NO borra inventario; solo elimina la marca de “asignado” para que el stock vuelva.)
     */
    public function deleteAssignedSeriesBySource(string $sourceType, int $sourceId): int
    {
        try {
            return (int) SerieReserva::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('estado', 'asignado')
                ->delete();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Alias compat: antes solo bloqueaba reservas de otros; ahora incluye asignados.
     */
    protected function serialesBloqueadosPorOtros(int $codigoProducto, ?string $token = null): array
    {
        return $this->serialesNoDisponibles($codigoProducto, $token);
    }

    /**
     * Si llega token, exigimos que los seriales elegidos estén reservados por ese token.
     */
    protected function assertSerialesReservadosPorToken(int $codigoProducto, array $seriales, string $token): void
    {
        $this->cleanupExpiredSerieReservas();

        $seriales = array_values(array_unique(
            array_filter(array_map(fn($s) => trim((string)$s), $seriales), fn($s) => $s !== '')
        ));
        if (empty($seriales)) return;

        $found = SerieReserva::query()
            ->where('codigo_producto', $codigoProducto)
            ->where('token', $token)
            ->where('estado', 'reservado')
            ->whereIn('numero_serie', $seriales)
            ->where(function ($qq) {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->pluck('numero_serie')
            ->filter()
            ->values()
            ->toArray();

        $set = array_flip($found);
        $missing = array_values(array_filter($seriales, fn($ns) => !isset($set[(string)$ns])));

        if (!empty($missing)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Algunos números de serie ya no están disponibles (no están reservados por esta captura).',
                'errors'  => ['productos' => ['N/S no disponibles: ' . implode(', ', $missing)]],
                'missing_serials' => $missing,
            ], 422));
        }
    }

    public function isSerialType(?string $tipo): bool
    {
        $t = strtolower(trim((string) $tipo));
        if ($t === 'piezas') return false;

        $compact = str_replace([' ', '.', '-', '_'], '', $t);
        if (Str::contains($compact, ['serie', 'serial', 'numerodeserie'])) {
            return true;
        }

        return preg_match('/\bns\b|n\/s/i', (string) $tipo) === 1;
    }

    /**
     * ✅ Devuelve TODOS los seriales disponibles para un producto.
     * - Prioridad 1: inventario.numero_serie
     * - Fallback: tabla numeros_serie
     * Filtra asignados y reservas de otros tokens.
     */
    public function peekSeriesAll(int $codigoProducto, ?string $token = null): array
    {
        $this->cleanupExpiredSerieReservas();

        if ($codigoProducto <= 0) return [];

        $token = $token ? trim((string)$token) : null;

        $bloqueados = $this->serialesNoDisponibles($codigoProducto, $token);
        $bloqSet = array_flip($bloqueados);

        $seriesInv = Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->where('numero_serie', '!=', '')
            ->orderBy('id')
            ->pluck('numero_serie')
            ->toArray();

        if (!empty($seriesInv)) {
            $seriesInv = array_values(array_filter($seriesInv, fn($ns) => !isset($bloqSet[(string)$ns])));
            return $seriesInv;
        }

        $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
        if ($invIds->isEmpty()) return [];

        $series = NumeroSerie::whereIn('inventario_id', $invIds)
            ->orderBy('id')
            ->pluck('numero_serie')
            ->toArray();

        if (!empty($series)) {
            $series = array_values(array_filter($series, fn($ns) => !isset($bloqSet[(string)$ns])));
        }

        return $series;
    }

    public function consumeAndPrepareLineItems(array $items, ?string $token = null): array
    {
        $final = [];

        foreach ($items as $it) {
            $codigo = $it['codigo_producto'] ?? null;
            $qty    = $this->quantityFrom($it);
            $desc   = $it['descripcion'] ?? null;

            $serials = [];
            if ($codigo && $qty > 0) {
                $preferidos = array_values(array_filter((array) ($it['ns_asignados'] ?? [])));
                $serials    = $this->allocateSerialsAndConsume((int) $codigo, $qty, $preferidos, $token);

                // ✅ Ya NO borramos filas de inventario.
                // Refrescamos stock_total del producto basándonos en asignados.
                $this->refreshProductStockTotals((int) $codigo);
            }

            if (!empty($serials)) {
                $desc = trim(($desc ? $desc . "\n" : '') . 'NS: ' . implode(', ', $serials));
            }

            $final[] = [
                'codigo_producto' => $codigo,
                'descripcion'     => $desc,
                'nombre_producto' => $it['nombre_producto'] ?? null,
                'cantidad'        => $qty,
                'precio'          => $this->unitPriceFrom($it),
                'ns_asignados'    => $serials,
            ];
        }

        return $final;
    }

    /**
     * Para preview NO consume inventario.
     * Si has_serial y no vienen ns_asignados, propone N seriales disponibles.
     */
    public function prepareLineItemsWithSerials(array $items, ?string $token = null): array
    {
        $final = [];

        foreach ($items as $it) {
            $codigo = $it['codigo_producto'] ?? null;
            $qty    = $this->quantityFrom($it);
            $desc   = $it['descripcion'] ?? null;

            $serials = array_values(array_filter((array) ($it['ns_asignados'] ?? [])));
            if ($codigo && $qty > 0 && empty($serials) && $this->productHasSerial((int)$codigo)) {
                $serials = $this->peekAvailableSerials((int) $codigo, $qty, $token);
            }

            if (!empty($serials)) {
                $desc = trim(($desc ? $desc . "\n" : '') . 'NS: ' . implode(', ', $serials));
            }

            $final[] = [
                'codigo_producto' => $codigo,
                'descripcion'     => $desc,
                'nombre_producto' => $it['nombre_producto'] ?? null,
                'cantidad'        => $qty,
                'precio'          => $this->unitPriceFrom($it),
                'ns_asignados'    => $serials,
            ];
        }

        return $final;
    }

    public function peekAvailableSerials(int $codigoProducto, float $cantidad, ?string $token = null): array
    {
        $needed = (int) ceil($cantidad);
        if ($needed <= 0) return [];

        $all = $this->peekSeriesAll($codigoProducto, $token);
        if (empty($all)) return [];

        return array_slice($all, 0, $needed);
    }

    /**
     * ✅ Consumir/seleccionar seriales sin borrar inventario:
     * - si NO es serial: consume FIFO de cantidades (como antes)
     * - si ES serial: valida preferidos y completa con FIFO, reservando al token si aplica.
     */
    public function allocateSerialsAndConsume(int $codigoProducto, float $cantidad, array $preferidos = [], ?string $token = null): array
    {
        $needed = (int) ceil($cantidad);
        if ($needed <= 0) return [];

        // No-serial: consumir cantidades
        if (!$this->productHasSerial($codigoProducto)) {
            $this->consumeNonSerialFIFO($codigoProducto, $needed);
            return [];
        }

        $collected = [];

        // 1) Preferred serials
        if (!empty($preferidos)) {
            if ($token) {
                $this->assertSerialesReservadosPorToken($codigoProducto, $preferidos, $token);
            }
            $tomados   = $this->allocateSpecificSerials($codigoProducto, $preferidos, $needed, $token);
            $collected = array_merge($collected, $tomados);
            $needed   -= count($tomados);
        }

        // 2) Completar FIFO (inventario)
        if ($needed > 0) {
            $fifo = $this->consumeSerialFIFOFromInventario($codigoProducto, $needed, $token);
            $collected = array_merge($collected, $fifo);
            $needed   -= count($fifo);
        }

        // 3) Fallback (numeros_serie)
        if ($needed > 0) {
            $fifo2 = $this->consumeSerialFIFOFromNumeroSerie($codigoProducto, $needed, $token);
            $collected = array_merge($collected, $fifo2);
            $needed   -= count($fifo2);
        }

        if ($needed > 0) {
            throw new HttpResponseException(response()->json([
                'message'   => "No hay numeros de serie suficientes para el producto {$codigoProducto}.",
                'errors'    => ['productos' => ["Faltan {$needed} numero(s) de serie del producto {$codigoProducto}."]],
                'shortages' => [[
                    'codigo_producto' => $codigoProducto,
                    'requerido'       => (int) ceil($cantidad),
                    'disponible'      => $this->calculateAvailableForProduct($codigoProducto, $token),
                    'faltante'        => $needed,
                ]],
            ], 422));
        }

        return $collected;
    }

    /**
     * Consume seriales específicos (NO borra inventario; solo valida que existan y estén disponibles).
     */
    public function allocateSpecificSerials(int $codigoProducto, array $preferidos, int $max, ?string $token = null): array
    {
        if ($max <= 0) return [];

        $preferidos = array_values(array_unique(
            array_filter($preferidos, fn($s) => is_string($s) && trim($s) !== '')
        ));
        if (empty($preferidos)) return [];

        $consumidos = [];
        $bloqSet = array_flip($this->serialesNoDisponibles($codigoProducto, $token));

        foreach ($preferidos as $ns) {
            if (count($consumidos) >= $max) break;

            $ns = trim((string)$ns);
            if ($ns === '') continue;

            // ocupado (asignado o reservado por otro token)
            if (isset($bloqSet[$ns])) {
                continue;
            }

            // Debe existir para ese producto
            if (!$this->serialExistsForProduct($codigoProducto, $ns)) {
                continue;
            }

            $consumidos[] = $ns;
        }

        return $consumidos;
    }

    protected function serialExistsForProduct(int $codigoProducto, string $ns): bool
    {
        $ns = trim($ns);
        if ($ns === '') return false;

        if (Inventario::where('codigo_producto', $codigoProducto)->where('numero_serie', $ns)->exists()) {
            return true;
        }

        $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
        if ($invIds->isEmpty()) return false;

        return NumeroSerie::where('numero_serie', $ns)->whereIn('inventario_id', $invIds)->exists();
    }

    /**
     * ✅ FIFO para seriales sin borrar inventario.
     * Si hay token: reserva los seriales seleccionados al token.
     */
    private function consumeSerialFIFOFromInventario(int $codigoProducto, int $needed, ?string $token = null): array
    {
        if ($needed <= 0) return [];

        $bloq = $this->serialesNoDisponibles($codigoProducto, $token);

        $q = Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->where('numero_serie', '!=', '')
            ->orderBy('id');

        if (!empty($bloq)) {
            $q->whereNotIn('numero_serie', $bloq);
        }

        $series = $q->limit($needed)->pluck('numero_serie')->filter()->values()->toArray();
        if (empty($series)) return [];

        if ($token) {
            // Reservar para esta captura (best-effort)
            $res = $this->reserveSeries($codigoProducto, $series, (string)$token, auth()->id() ?? null);
            $reserved = array_values(array_filter((array) ($res['reserved'] ?? [])));
            return $reserved;
        }

        // Sin token: devolvemos los seriales (la asignación final debe registrarse en DetalleOrdenProductoSerie o SerieReserva->asignado)
        return $series;
    }

    private function consumeSerialFIFOFromNumeroSerie(int $codigoProducto, int $needed, ?string $token = null): array
    {
        if ($needed <= 0) return [];

        $bloq = $this->serialesNoDisponibles($codigoProducto, $token);

        $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
        if ($invIds->isEmpty()) return [];

        $q = NumeroSerie::whereIn('inventario_id', $invIds)->orderBy('id');

        if (!empty($bloq)) {
            $q->whereNotIn('numero_serie', $bloq);
        }

        $series = $q->limit($needed)->pluck('numero_serie')->filter()->values()->toArray();
        if (empty($series)) return [];

        if ($token) {
            $res = $this->reserveSeries($codigoProducto, $series, (string)$token, auth()->id() ?? null);
            $reserved = array_values(array_filter((array) ($res['reserved'] ?? [])));
            return $reserved;
        }

        return $series;
    }

    private function consumeNonSerialFIFO(int $codigoProducto, int $needed): void
    {
        if ($needed <= 0) return;

        $entradas = Inventario::where('codigo_producto', $codigoProducto)
            ->orderBy('fecha_entrada')
            ->orderBy('id')
            ->get();

        foreach ($entradas as $ent) {
            if ($needed <= 0) break;

            // no tocar entradas seriales
            if ($this->isSerialType((string)$ent->tipo_control)) {
                continue;
            }
            $ns = $ent->numero_serie ?? null;
            if ($ns !== null && trim((string)$ns) !== '') {
                continue;
            }

            $ppp     = max((int) ($ent->piezas_por_paquete ?? 0), 0);
            $packs   = max((int) ($ent->paquetes_restantes ?? 0), 0);
            $sueltas = max((int) ($ent->piezas_sueltas ?? 0), 0);

            // fallback legacy: cantidad_ingresada
            if ($ppp === 0 && $packs === 0 && $sueltas === 0) {
                $ci = max((int) ($ent->cantidad_ingresada ?? 0), 0);
                if ($ci > 0) {
                    $take = min($ci, $needed);
                    $ent->cantidad_ingresada = $ci - $take;
                    $needed -= $take;
                    $ent->save();
                    continue;
                }
            }

            if ($sueltas > 0 && $needed > 0) {
                $take                = min($sueltas, $needed);
                $ent->piezas_sueltas = $sueltas - $take;
                $needed              -= $take;
                $sueltas             = $ent->piezas_sueltas;
            }

            if ($packs > 0 && $needed > 0) {
                if ($ppp <= 0) {
                    $takePacks = min($packs, $needed);
                    $packs -= $takePacks;
                    $needed -= $takePacks;
                    $ent->paquetes_restantes = $packs;
                } else {
                    while ($needed > 0 && $packs > 0) {
                        if ($needed >= $ppp) {
                            $needed -= $ppp;
                            $packs--;
                        } else {
                            $packs--;
                            $ent->piezas_sueltas = ($ent->piezas_sueltas ?? 0) + ($ppp - $needed);
                            $needed = 0;
                        }
                    }
                    $ent->paquetes_restantes = $packs;
                }
            }

            $ent->save();
        }

        if ($needed > 0) {
            throw new HttpResponseException(response()->json([
                'message'   => "No hay stock suficiente para el producto {$codigoProducto}.",
                'errors'    => ['productos' => ["Faltan {$needed} pieza(s) del producto {$codigoProducto}."]],
                'shortages' => [[
                    'codigo_producto' => $codigoProducto,
                    'requerido'       => $needed,
                    'disponible'      => $this->calculateAvailableForProduct($codigoProducto),
                    'faltante'        => $needed,
                ]],
            ], 422));
        }
    }

    /**
     * ✅ Actualiza stock_total del producto SIN eliminar inventario.
     * Para serial-controlados: stock_total = totalSeriales - asignados (SerieReserva.estado=asignado)
     */
    /**
     * Pool de seriales reales para un producto.
     * - inventario.numero_serie (tu caso principal)
     * - fallback: tabla numeros_serie (si se usa)
     */
    protected function serialPoolForProduct(int $codigoProducto): array
    {
        $fromInv = Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->where('numero_serie', '!=', '')
            ->orderBy('id')
            ->pluck('numero_serie')
            ->toArray();

        $invIds = Inventario::where('codigo_producto', $codigoProducto)
            ->orderBy('id')
            ->pluck('id');

        $fromTable = [];
        if ($invIds->isNotEmpty()) {
            $fromTable = NumeroSerie::whereIn('inventario_id', $invIds)
                ->orderBy('inventario_id')
                ->orderBy('id')
                ->pluck('numero_serie')
                ->toArray();
        }

        $all = array_merge($fromInv, $fromTable);
        $all = array_map(fn($s) => trim((string) $s), $all);
        $all = array_values(array_unique(array_filter($all, fn($s) => $s !== '')));

        return $all;
    }

    function refreshProductStockTotals(int $codigoProducto): void
    {
        $entradas = Inventario::where('codigo_producto', $codigoProducto)->get([
            'id',
            'tipo_control',
            'paquetes_restantes',
            'piezas_por_paquete',
            'piezas_sueltas',
            'cantidad_ingresada',
            'numero_serie',
        ]);

        if ($entradas->isEmpty()) {
            Producto::where('codigo_producto', $codigoProducto)->update([
                'stock_total'          => 0,
                'stock_paquetes'       => 0,
                'stock_piezas_sueltas' => 0,
            ]);
            return;
        }

        // ===== NO SERIAL =====
        $paquetes = 0;
        $sueltas  = 0;
        $total    = 0;

        foreach ($entradas as $e) {
            $ns = $e->numero_serie ?? null;
            if ($ns !== null && trim((string)$ns) !== '') continue;
            if ($this->isSerialType($e->tipo_control)) continue;

            $ppp   = max((int) ($e->piezas_por_paquete ?? 0), 0);
            $packs = max((int) ($e->paquetes_restantes ?? 0), 0);
            $slt   = max((int) ($e->piezas_sueltas ?? 0), 0);

            if ($ppp === 0 && $packs === 0 && $slt === 0) {
                $ci = max((int) ($e->cantidad_ingresada ?? 0), 0);
                if ($ci > 0) $slt += $ci;
            }

            $paquetes += $packs;
            $sueltas  += $slt;

            if ($packs > 0) {
                $total += ($ppp > 0) ? ($packs * $ppp) : $packs;
            }
            $total += $slt;
        }

        // ===== SERIAL (FIX: solo restar seriales asignados que existan en el pool real) =====
        if ($this->productHasSerial($codigoProducto)) {
            $pool = $this->serialPoolForProduct($codigoProducto);

            $asignados = [];
            try {
                $asignados = SerieReserva::where('codigo_producto', $codigoProducto)
                    ->where('estado', 'asignado')
                    ->pluck('numero_serie')
                    ->toArray();
            } catch (\Throwable $e) {
                $asignados = [];
            }

            $asgSet = array_flip(array_map('strval', (array) $asignados));
            $disponibles = array_values(array_filter($pool, fn($ns) => !isset($asgSet[(string)$ns])));

            $available = count($disponibles);
            $total    = $available;
            $paquetes = 0;
            $sueltas  = $available;
        }

        Producto::where('codigo_producto', $codigoProducto)->update([
            'stock_total'          => (int) $total,
            'stock_paquetes'       => (int) $paquetes,
            'stock_piezas_sueltas' => (int) $sueltas,
        ]);
    }

    public function extractSerialsFromText(string $text): array
    {
        if (!preg_match('/NS:\s*(.+)$/mi', $text, $m)) return [];

        $list = array_map('trim', explode(',', $m[1]));
        return array_values(array_filter($list, fn($s) => $s !== ''));
    }

    /* ===================== STOCK ===================== */

    /**
     * ✅ Stock real:
     * - si el producto es serial: totalSeriales - (asignados + reservados por otros tokens)
     * - si NO es serial: suma piezas disponibles (paquetes + sueltas + legacy cantidad_ingresada)
     */
    public function calculateAvailableForProduct(int $codigo, ?string $token = null): int
    {
        if ($codigo <= 0) return 0;

        $this->cleanupExpiredSerieReservas();

        $entradas = Inventario::where('codigo_producto', $codigo)->get([
            'id',
            'tipo_control',
            'paquetes_restantes',
            'piezas_por_paquete',
            'piezas_sueltas',
            'cantidad_ingresada',
            'numero_serie',
        ]);

        if ($entradas->isEmpty()) return 0;

        // ===== NO SERIAL =====
        $stockNoSerial = 0;
        foreach ($entradas as $e) {
            $ns = $e->numero_serie ?? null;
            if ($ns !== null && trim((string)$ns) !== '') continue;
            if ($this->isSerialType($e->tipo_control)) continue;

            $ppp     = max((int) ($e->piezas_por_paquete ?? 0), 0);
            $packs   = max((int) ($e->paquetes_restantes ?? 0), 0);
            $sueltas = max((int) ($e->piezas_sueltas ?? 0), 0);

            $piezas = 0;

            if ($packs > 0) {
                $piezas += ($ppp > 0) ? ($packs * $ppp) : $packs;
            }

            $piezas += $sueltas;

            if ($piezas <= 0) {
                $ci = max((int) ($e->cantidad_ingresada ?? 0), 0);
                if ($ci > 0) $piezas += $ci;
            }

            $stockNoSerial += $piezas;
        }

        // ===== SERIAL (FIX: por intersección, no por "count") =====
        if ($this->productHasSerial($codigo)) {
            $pool = $this->serialPoolForProduct($codigo);
            if (empty($pool)) return 0;

            $bloqueados = $this->serialesNoDisponibles($codigo, $token);
            $bloqSet = array_flip(array_map('strval', (array) $bloqueados));

            $disponibles = array_values(array_filter($pool, fn($ns) => !isset($bloqSet[(string)$ns])));

            return count($disponibles);
        }

        return max((int) $stockNoSerial, 0);
    }

    public function preflightStockCheck(array $items, ?string $token = null): array
    {
        $shortages = [];
        $annotated = [];

        foreach ($items as $it) {
            $codigo = (int) ($it['codigo_producto'] ?? 0);
            $qty    = (int) ceil($this->quantityFrom($it));

            $row             = $it;
            $row['cantidad'] = $qty;

            if ($codigo > 0 && $qty > 0) {
                $available = $this->calculateAvailableForProduct($codigo, $token);
                $faltante  = max($qty - $available, 0);

                $row['stock_disponible'] = $available;
                $row['stock']            = $available;
                $row['disponible']       = $available;
                $row['stock_max']        = $available;
                $row['faltante']         = $faltante;
                $row['sin_stock']        = $faltante > 0;
                $row['has_serial']       = $this->productHasSerial($codigo);

                if ($faltante > 0) {
                    $shortages[] = [
                        'codigo_producto' => $codigo,
                        'nombre'          => $it['nombre_producto'] ?? ($it['descripcion'] ?? 'Producto'),
                        'requerido'       => $qty,
                        'disponible'      => $available,
                        'faltante'        => $faltante,
                    ];
                }
            } else {
                $row['stock_disponible'] = null;
                $row['stock']            = null;
                $row['disponible']       = null;
                $row['stock_max']        = null;
                $row['faltante']         = 0;
                $row['sin_stock']        = false;
            }

            $annotated[] = $row;
        }

        return [
            'ok'        => empty($shortages),
            'shortages' => $shortages,
            'annotated' => $annotated,
        ];
    }

    public function productHasSerial(int $codigoProducto): bool
    {
        if ($codigoProducto <= 0) return false;

        if (Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->where('numero_serie', '!=', '')
            ->exists()
        ) {
            return true;
        }

        $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
        if ($invIds->isNotEmpty() && NumeroSerie::whereIn('inventario_id', $invIds)->exists()) {
            return true;
        }

        $tipo = Inventario::where('codigo_producto', $codigoProducto)
            ->orderByDesc('fecha_entrada')
            ->orderByDesc('id')
            ->value('tipo_control');

        return $this->isSerialType($tipo);
    }

    public function failIfShortage(array $check): void
    {
        if (!($check['ok'] ?? true)) {
            throw new HttpResponseException(response()->json([
                'ok'                => false,
                'message'           => 'Hay productos sin stock suficiente.',
                'shortages'         => $check['shortages'],
                'productos_preview' => $check['annotated'],
            ], 422));
        }
    }

    /* ===================== Crédito ===================== */

    public function checkCreditoVencido(CreditoCliente $credito): array
    {
        $diasCredito = (int) ($credito->dias_credito ?? 0);

        if (!empty($credito->fecha_asignacion)) {
            $inicio = Carbon::parse($credito->fecha_asignacion)->startOfDay();
        } elseif (!empty($credito->created_at)) {
            $inicio = Carbon::parse($credito->created_at)->startOfDay();
        } else {
            $inicio = Carbon::today();
        }

        if ($diasCredito > 0) {
            $fechaLimite = $inicio->copy()->addDays($diasCredito);
        } elseif (!empty($credito->fecha_limite)) {
            $fechaLimite = Carbon::parse($credito->fecha_limite)->endOfDay();
        } else {
            return ['expired' => false, 'fecha_limite' => null, 'dias_restantes' => null];
        }

        $hoy = Carbon::today();

        if ($hoy->gt($fechaLimite)) {
            $diasRestantes = -$hoy->diffInDays($fechaLimite);
            return [
                'expired'        => true,
                'fecha_limite'   => $fechaLimite->toDateString(),
                'dias_restantes' => $diasRestantes,
            ];
        }

        return [
            'expired'        => false,
            'fecha_limite'   => $fechaLimite->toDateString(),
            'dias_restantes' => $hoy->diffInDays($fechaLimite),
        ];
    }
}
