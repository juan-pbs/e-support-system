<?php

namespace App\Services\Ordenes;

use App\Models\Cliente;
use App\Models\CreditoCliente;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\Inventario;
use App\Models\NumeroSerie;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\SerieReserva;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdenServicioService
{
    protected array $columnCache = [];

    /* =========================================================
     |  Column helpers
     * ========================================================= */
    protected function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (array_key_exists($key, $this->columnCache)) {
            return (bool) $this->columnCache[$key];
        }

        try {
            $this->columnCache[$key] = Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            $this->columnCache[$key] = false;
        }

        return (bool) $this->columnCache[$key];
    }

    protected function ordenHasColumn(string $column): bool
    {
        return $this->hasColumn('orden_servicio', $column);
    }

    protected function productoHasColumn(string $column): bool
    {
        return $this->hasColumn('productos', $column);
    }

    /* =========================================================
     |  Firma digital (sin depender de trait externo)
     * ========================================================= */
    protected function firmaDefaultMetaPath(): string
    {
        return 'private/ordenes/firmas/firma_default_orden.json';
    }

    protected function readFirma(): array
    {
        $default = [
            'nombre'  => '',
            'puesto'  => '',
            'empresa' => '',
            'image'   => null,
        ];

        try {
            $path = $this->firmaDefaultMetaPath();

            if (!Storage::exists($path)) {
                return $default;
            }

            $json = json_decode((string) Storage::get($path), true);

            if (!is_array($json)) {
                return $default;
            }

            return array_merge($default, [
                'nombre'  => (string) ($json['nombre'] ?? ''),
                'puesto'  => (string) ($json['puesto'] ?? ''),
                'empresa' => (string) ($json['empresa'] ?? ''),
                'image'   => !empty($json['image']) ? (string) $json['image'] : null,
            ]);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function getFirma(): array
    {
        return $this->readFirma();
    }

    public function ensurePrivateOrdenDirs(): void
    {
        foreach (
            [
                'private/ordenes',
                'private/ordenes/actas',
                'private/ordenes/firmas',
            ] as $dir
        ) {
            if (!Storage::exists($dir)) {
                Storage::makeDirectory($dir);
            }
        }
    }

    protected function saveFirmaDefaultFromRequest(Request $request): void
    {
        $guardar = $request->boolean('firma_guardar_default');
        if (!$guardar) {
            return;
        }

        $firmaRaw = $request->input('firma_base64')
            ?: $request->input('firma_autorizacion')
            ?: $request->input('firma_autorizacion_base64');

        $firmaDataUri = $this->normalizeDataUriImage($firmaRaw);
        if (!$firmaDataUri) {
            return;
        }

        $this->ensurePrivateOrdenDirs();

        $filename = 'private/ordenes/firmas/firma_default_orden_' . uniqid() . '.png';
        $b64      = preg_replace('#^data:image/[^;]+;base64,#i', '', $firmaDataUri);
        $binary   = base64_decode((string) $b64, true);

        if ($binary === false) {
            return;
        }

        Storage::put($filename, $binary);

        $payload = [
            'nombre'  => (string) $request->input('firma_nombre', ''),
            'puesto'  => (string) $request->input('firma_puesto', ''),
            'empresa' => (string) $request->input('firma_empresa', ''),
            'image'   => $firmaDataUri,
        ];

        Storage::put($this->firmaDefaultMetaPath(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /* =========================================================
     |  Form create / catálogos
     * ========================================================= */
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
            $p->imagen_url = $this->resolveProductImageUrl($p->imagen ?? null);

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

    protected function resolveProductImageUrl(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return asset('images/imagen.png');
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:image/'])) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    /* =========================================================
     |  Anticipo
     * ========================================================= */
    public function computeAnticipoFromData(array $data, float $totalOrden): array
    {
        $modo       = (string) ($data['anticipo_modo'] ?? '');
        $totalOrden = max((float) $totalOrden, 0.0);
        $anticipo   = 0.0;

        $pctIn = $data['anticipo_porcentaje'] ?? null;

        if ($pctIn !== null && $pctIn !== '') {
            $modo = 'porcentaje';
            $pct  = (float) $pctIn;
            $pct  = max(min($pct, 100), 0);
            $anticipo = $totalOrden * ($pct / 100);
        } else {
            $modo  = 'monto';
            $monto = $data['anticipo'] ?? ($data['anticipo_monto'] ?? 0);
            $monto = (float) $monto;
            $anticipo = max($monto, 0);
        }

        $anticipo = min($anticipo, $totalOrden);
        $anticipo = round(max($anticipo, 0), 2);
        $saldo    = round(max($totalOrden - $anticipo, 0), 2);
        $pctCalc  = $totalOrden > 0 ? round(($anticipo / $totalOrden) * 100, 2) : 0;

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
        $calc       = $this->computeAnticipoFromData($data, $totalOrden);

        $anticipo = (float) $calc['anticipo'];
        $saldo    = (float) $calc['saldo'];

        $moneda = strtoupper((string) ($orden->moneda ?? 'MXN'));
        $tc     = (float) ($orden->tasa_cambio ?? 0);

        $totalMXN    = $totalOrden;
        $anticipoMXN = $anticipo;
        $saldoMXN    = $saldo;

        if ($moneda === 'USD') {
            if ($tc > 0) {
                $totalMXN    = round($totalOrden * $tc, 2);
                $anticipoMXN = round($anticipo * $tc, 2);
                $saldoMXN    = round($saldo * $tc, 2);
            } else {
                $totalMXN = $anticipoMXN = $saldoMXN = 0;
            }
        }

        if (isset($data['anticipo_mxn']) && $data['anticipo_mxn'] !== '' && $data['anticipo_mxn'] !== null) {
            $anticipoMXN = round(max((float) $data['anticipo_mxn'], 0), 2);
            $anticipoMXN = min($anticipoMXN, $totalMXN);

            if ($moneda === 'USD') {
                $anticipo = $tc > 0 ? round($anticipoMXN / $tc, 2) : 0;
            } else {
                $anticipo = $anticipoMXN;
            }

            $saldo    = round(max($totalOrden - $anticipo, 0), 2);
            $saldoMXN = round(max($totalMXN - $anticipoMXN, 0), 2);
        }

        if (isset($data['anticipo_porcentaje']) && $data['anticipo_porcentaje'] !== '' && $data['anticipo_porcentaje'] !== null) {
            $pct = max(min((float) $data['anticipo_porcentaje'], 100), 0);
        } else {
            $pct = $totalMXN > 0 ? round(($anticipoMXN / $totalMXN) * 100, 2) : 0;
        }

        if ($this->ordenHasColumn('anticipo_mxn')) {
            $orden->anticipo_mxn = $anticipoMXN;
        }

        if ($this->ordenHasColumn('anticipo_porcentaje')) {
            $orden->anticipo_porcentaje = $pct;
        }

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

    /* =========================================================
     |  Validación / fill
     * ========================================================= */
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
            'id_cliente'                => ['required', 'integer', 'exists:cliente,clave_cliente'],
            'servicio'                  => ['nullable', 'string'],
            'tipo_orden'                => ['required', 'in:compra,servicio_simple,servicio_proyecto'],
            'prioridad'                 => ['required', 'in:Baja,Media,Alta,Urgente'],
            'estado'                    => ['nullable', 'string'],
            'id_tecnico'                => ['nullable', 'integer', 'exists:users,id'],
            'tecnicos_ids'              => ['nullable', 'array'],
            'tecnicos_ids.*'            => ['integer', 'exists:users,id'],
            'tipo_pago'                 => ['nullable', 'string'],
            'facturado'                 => ['nullable', 'boolean'],
            'precio'                    => ['nullable', 'numeric'],
            'costo_operativo'           => ['nullable', 'numeric'],
            'descripcion'               => ['nullable', 'string'],
            'descripcion_servicio'      => ['nullable', 'string'],
            'condiciones_generales'     => ['nullable', 'string'],
            'moneda'                    => ['nullable', 'in:MXN,USD'],
            'tasa_cambio'               => ['nullable', 'numeric'],
            'fecha_programada'          => ['nullable', 'date'],
            'fecha_compromiso'          => ['nullable', 'date'],
            'fecha_orden'               => ['nullable', 'date'],
            'anticipo_mxn'              => ['nullable', 'numeric', 'min:0'],
            'anticipo_porcentaje'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'anticipo_modo'             => ['nullable', 'in:monto,porcentaje'],
            'anticipo_monto'            => ['nullable', 'numeric', 'min:0'],
            'anticipo'                  => ['nullable', 'numeric', 'min:0'],
            'total_orden'               => ['nullable', 'numeric'],
            'precio_escrito'            => ['nullable', 'string', 'max:255'],
            'productos'                 => ['nullable', 'array'],
            'productos.*.codigo_producto' => ['nullable'],
            'productos.*.descripcion'   => ['nullable', 'string'],
            'productos.*.nombre_producto' => ['nullable', 'string', 'max:255'],
            'productos.*.cantidad'      => ['nullable', 'numeric'],
            'productos.*.precio'        => ['nullable', 'numeric'],
            'productos.*.ns_asignados'  => ['nullable', 'array'],
            'productos.*.ns_asignados.*' => ['nullable', 'string'],
            'firma_base64'              => ['nullable', 'string'],
            'firma_svg'                 => ['nullable', 'string'],
            'firma_nombre'              => ['nullable', 'string', 'max:255'],
            'firma_puesto'              => ['nullable', 'string', 'max:255'],
            'firma_empresa'             => ['nullable', 'string', 'max:255'],
            'firma_guardar_default'     => ['nullable'],
            'firma_autorizacion'        => ['nullable', 'string'],
            'firma_autorizacion_base64' => ['nullable', 'string'],
            'serial_token'              => ['nullable', 'string', 'max:120'],
        ];

        if ($fromCotizacion) {
            $rules['cotizacion_id']     = ['required', 'integer', 'exists:cotizaciones,id_cotizacion'];
            $rules['estado_cotizacion'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        if (!empty($data['fecha_programada']) && empty($data['fecha_orden'])) {
            $data['fecha_orden'] = Carbon::today()->toDateString();
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
        $set = function (string $column, $value) use ($orden): void {
            if ($this->ordenHasColumn($column)) {
                $orden->{$column} = $value;
            }
        };

        $set('id_cliente', $data['id_cliente']);
        $set('servicio', $data['servicio'] ?? $orden->servicio ?? null);
        $set('tipo_orden', $data['tipo_orden']);
        $set('prioridad', $data['prioridad']);
        $set('estado', $data['estado'] ?? ($orden->estado ?? 'Pendiente'));
        $set('id_tecnico', $data['id_tecnico'] ?? ($data['tecnicos_ids'][0] ?? null));
        $set('tipo_pago', $data['tipo_pago'] ?? null);
        if (array_key_exists('facturado', $data)) {
            $set('facturado', (int) ($data['facturado'] ?? 0) === 1);
        }
        $set('precio', (float) ($data['precio'] ?? 0));
        $set('costo_operativo', (float) ($data['costo_operativo'] ?? 0));
        $set('moneda', $data['moneda'] ?? ($orden->moneda ?? 'MXN'));
        $set('tasa_cambio', $data['tasa_cambio'] ?? ($orden->tasa_cambio ?? null));
        $set('descripcion', $data['descripcion'] ?? null);
        $set('descripcion_servicio', $data['descripcion_servicio'] ?? ($orden->descripcion_servicio ?? null));
        $set(
            'precio_escrito',
            $this->normalizeOptionalText($data['precio_escrito'] ?? ($orden->precio_escrito ?? null))
        );
        $set(
            'condiciones_generales',
            (isset($data['condiciones_generales']) && $data['condiciones_generales'] !== '')
                ? $data['condiciones_generales']
                : null
        );

        $fechaOrden = !empty($data['fecha_orden'])
            ? Carbon::parse($data['fecha_orden'])->toDateString()
            : ($orden->fecha_orden ?? Carbon::today()->toDateString());

        $set('fecha_orden', $fechaOrden);

        if (isset($data['anticipo_porcentaje']) && $data['anticipo_porcentaje'] !== '' && $data['anticipo_porcentaje'] !== null) {
            $set('anticipo_porcentaje', (float) $data['anticipo_porcentaje']);
        }

        if (isset($data['anticipo_mxn']) && $data['anticipo_mxn'] !== '' && $data['anticipo_mxn'] !== null) {
            $set('anticipo_mxn', (float) $data['anticipo_mxn']);
        }
    }

    protected function normalizeOptionalText($value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    public function resolvePrecioEscrito($value, float $total, string $moneda): string
    {
        $text = $this->normalizeOptionalText($value);

        if ($text !== null) {
            return $text;
        }

        return $this->moneyToWordsEs($total, $moneda);
    }

    protected function moneyToWordsEs(float $amount, string $currency = 'MXN'): string
    {
        $amount = round($amount, 2);
        $integer = (int) floor($amount);
        $cents = (int) round(($amount - $integer) * 100);

        if ($cents === 100) {
            $integer++;
            $cents = 0;
        }

        $currency = strtoupper(trim($currency));
        $words = $this->adjustWordsForNoun($this->numberToWordsEs($integer));
        $noun = $currency === 'USD'
            ? ($integer === 1 ? 'DOLAR' : 'DOLARES')
            : ($integer === 1 ? 'PESO' : 'PESOS');
        $suffix = $currency === 'USD' ? 'USD' : 'M.N.';

        return sprintf('%s %s %02d/100 %s', $words, $noun, $cents, $suffix);
    }

    protected function adjustWordsForNoun(string $words): string
    {
        $words = preg_replace('/VEINTIUNO$/', 'VEINTIUN', $words);
        $words = preg_replace('/ Y UNO$/', ' Y UN', $words);
        $words = preg_replace('/ UNO$/', ' UN', $words);

        return $words;
    }

    protected function numberToWordsEs(int $number): string
    {
        $units = [
            0 => 'CERO',
            1 => 'UNO',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
        ];

        $specials = [
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            16 => 'DIECISEIS',
            17 => 'DIECISIETE',
            18 => 'DIECIOCHO',
            19 => 'DIECINUEVE',
            20 => 'VEINTE',
            21 => 'VEINTIUNO',
            22 => 'VEINTIDOS',
            23 => 'VEINTITRES',
            24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO',
            26 => 'VEINTISEIS',
            27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO',
            29 => 'VEINTINUEVE',
        ];

        $tens = [
            3 => 'TREINTA',
            4 => 'CUARENTA',
            5 => 'CINCUENTA',
            6 => 'SESENTA',
            7 => 'SETENTA',
            8 => 'OCHENTA',
            9 => 'NOVENTA',
        ];

        $hundreds = [
            1 => 'CIENTO',
            2 => 'DOSCIENTOS',
            3 => 'TRESCIENTOS',
            4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS',
            6 => 'SEISCIENTOS',
            7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS',
            9 => 'NOVECIENTOS',
        ];

        if ($number < 10) {
            return $units[$number];
        }

        if ($number < 30) {
            return $specials[$number];
        }

        if ($number < 100) {
            $ten = (int) floor($number / 10);
            $rest = $number % 10;

            return $tens[$ten] . ($rest ? ' Y ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number === 100) {
            return 'CIEN';
        }

        if ($number < 1000) {
            $hundred = (int) floor($number / 100);
            $rest = $number % 100;

            return $hundreds[$hundred] . ($rest ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number < 2000) {
            return 'MIL' . ($number % 1000 ? ' ' . $this->numberToWordsEs($number % 1000) : '');
        }

        if ($number < 1000000) {
            $thousands = (int) floor($number / 1000);
            $rest = $number % 1000;

            return $this->numberToWordsEs($thousands) . ' MIL' . ($rest ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number < 2000000) {
            return 'UN MILLON' . ($number % 1000000 ? ' ' . $this->numberToWordsEs($number % 1000000) : '');
        }

        if ($number < 1000000000000) {
            $millions = (int) floor($number / 1000000);
            $rest = $number % 1000000;

            return $this->adjustWordsForNoun($this->numberToWordsEs($millions))
                . ' MILLONES'
                . ($rest ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        return (string) $number;
    }

    public function normalizeDataUriImage(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

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
            $firmaRaw = $request->input('firma_autorizacion') ?: $request->input('firma_autorizacion_base64');
        }

        $firmaPref      = $this->getFirma();
        $firmaParaOrden = $firmaRaw ?: ($firmaPref['image'] ?? null);
        $firmaDataUri   = $this->normalizeDataUriImage($firmaParaOrden);

        if ($firmaDataUri) {
            $this->ensurePrivateOrdenDirs();

            $b64    = preg_replace('#^data:image/[^;]+;base64,#i', '', $firmaDataUri);
            $binary = base64_decode((string) $b64, true);

            if ($binary !== false) {
                $filename = 'private/ordenes/firmas/firma_orden_' . uniqid() . '.png';
                Storage::put($filename, $binary);

                if ($this->ordenHasColumn('firma_conformidad')) {
                    $orden->firma_conformidad = $filename;
                }
            }
        }

        $this->saveFirmaDefaultFromRequest($request);
    }

    /* =========================================================
     |  PDF definitivo
     * ========================================================= */
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
        $orden = OrdenServicio::with([
            'cliente',
            'tecnico',
            'tecnicos',
            'materialesExtras',
            'productos.series',
        ])->findOrFail($ordenId);

        try {
            if (method_exists($orden, 'recalcularTotalAdicionalMxn')) {
                $orden->recalcularTotalAdicionalMxn();
                $orden->refresh();
            }
        } catch (\Throwable $e) {
            // noop
        }

        $detalles = DetalleOrdenProducto::with('series')
            ->where('id_orden_servicio', $orden->getKey())
            ->get();

        $productos = $detalles->map(function ($p) {
            $desc    = $p->descripcion ?? $p->detalle ?? null;
            $serials = [];

            // ✅ prioridad: tomar seriales desde la relación
            if ($p->relationLoaded('series')) {
                $serials = $p->series
                    ->pluck('numero_serie')
                    ->filter()
                    ->values()
                    ->toArray();
            }

            // fallback
            if (empty($serials)) {
                $serials = $this->extractSerialsFromText((string) ($desc ?? ''));
            }

            $qty = !empty($serials)
                ? count($serials)
                : (float) ($p->cantidad ?? 0);

            $pu = (float) ($p->precio_unitario ?? 0);

            return (object) [
                'nombre_producto' => $p->nombre_producto ?? ($desc ?? 'Producto'),
                'descripcion'     => $desc,
                'detalle'         => null,
                'cantidad'        => $qty,
                'precio_unitario' => $pu,
                'total'           => max(($qty * $pu), 0),
                'ns_asignados'    => $serials,
                'series'          => collect($serials)->map(fn($ns) => (object) ['numero_serie' => $ns]),
            ];
        });

        $lineasForTotals = $detalles->map(function ($p) {
            $serials = $p->relationLoaded('series')
                ? $p->series->pluck('numero_serie')->filter()->values()->toArray()
                : [];

            return [
                'cantidad'     => !empty($serials) ? count($serials) : (float) ($p->cantidad ?? 0),
                'precio'       => (float) ($p->precio_unitario ?? 0),
                'ns_asignados' => $serials,
            ];
        })->toArray();

        $adicional = 0.0;
        try {
            $adicional = (float) ($orden->total_adicional ?? 0);
        } catch (\Throwable $e) {
            $adicional = 0.0;
        }

        $totales = $this->calculateTotals(
            $lineasForTotals,
            (float) ($orden->precio ?? 0),
            (float) ($orden->costo_operativo ?? 0),
            $adicional
        );

        if ($this->ordenHasColumn('impuestos')) {
            $orden->impuestos = (float) ($totales['iva'] ?? 0);
            $orden->save();
        }

        $cliente      = $orden->cliente;
        $firma        = $this->getFirma();
        $firma_base64 = null;
        $pathFirma    = $orden->firma_conformidad ?? null;

        if (!empty($pathFirma) && Storage::exists($pathFirma)) {
            try {
                $bin  = Storage::get($pathFirma);
                $mime = Storage::mimeType($pathFirma) ?: 'image/png';
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

        if ($this->ordenHasColumn('archivo_pdf')) {
            $orden->archivo_pdf = $path;
            $orden->save();
        }

        return $path;
    }

    /* =========================================================
     |  Helpers de líneas / totales
     * ========================================================= */
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
            $n = count(array_filter($item['ns_asignados'], fn($s) => is_string($s) && trim($s) !== ''));
            if ($n > 0) {
                return (float) $n;
            }
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
        $hasMoneda = $this->hasColumn('detalle_orden_producto', 'moneda');
        $touched   = [];

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
            $serials     = array_values(array_filter((array) ($item['ns_asignados'] ?? []), fn($x) => trim((string) $x) !== ''));

            if (!empty($serials)) {
                $descHasNs = is_string($descripcion) && stripos($descripcion, 'NS:') !== false;

                if (!$descHasNs) {
                    $descripcion = trim(($descripcion ? $descripcion . "\n" : '') . 'NS: ' . implode(', ', $serials));
                }
            }

            $totalLinea = round(($cantidad * $precio), 2);
            if ($totalLinea < 0) {
                $totalLinea = 0;
            }

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

            /** @var \App\Models\DetalleOrdenProducto $detalle */
            $detalle = DetalleOrdenProducto::create($insert);

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

            if (!empty($item['codigo_producto'])) {
                $touched[] = (int) $item['codigo_producto'];
            }
        }

        foreach (array_values(array_unique($touched)) as $codigo) {
            $this->refreshProductStockTotals((int) $codigo);
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

        if ($this->ordenHasColumn('impuestos')) {
            $orden->impuestos = $iva;
            $orden->save();
        }
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
            'adicional' => round((float) $adicional, 2),
            'base'      => round($base, 2),
            'iva'       => $iva,
            'subtotal'  => round($subtotal, 2),
            'total'     => $total,
        ];
    }

    public function extractSerialsFromText(?string $text): array
    {
        if (!$text) {
            return [];
        }

        if (!preg_match('/NS:\s*(.+)$/mi', (string) $text, $m)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($x) => trim((string) $x),
            explode(',', (string) ($m[1] ?? ''))
        ), fn($x) => $x !== ''));
    }

    /* =========================================================
     |  Inventario / series
     * ========================================================= */
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

    protected function serialesNoDisponibles(int $codigoProducto, ?string $token = null): array
    {
        $this->cleanupExpiredSerieReservas();

        try {
            return SerieReserva::query()
                ->where('codigo_producto', $codigoProducto)
                ->where(function ($qq) use ($token) {
                    $qq->where('estado', 'asignado')
                        ->orWhere(function ($q2) use ($token) {
                            $q2->where('estado', 'reservado')
                                ->where(function ($q3) {
                                    $q3->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', now());
                                });

                            if ($token) {
                                $q2->where('token', '!=', $token);
                            }
                        });
                })
                ->pluck('numero_serie')
                ->filter()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function reserveSeries(
        int $codigoProducto,
        array $seriales,
        string $token,
        ?int $userId = null,
        int $ttlMinutes = 30
    ): array {
        $this->cleanupExpiredSerieReservas();

        $codigoProducto = (int) $codigoProducto;
        $token          = trim((string) $token);
        $ttlMinutes     = max((int) $ttlMinutes, 5);
        $expiresAt      = now()->addMinutes($ttlMinutes);

        $seriales = array_values(array_unique(array_filter(
            array_map(fn($s) => trim((string) $s), (array) $seriales),
            fn($s) => $s !== ''
        )));

        if ($codigoProducto <= 0 || $token === '' || empty($seriales)) {
            return [
                'ok'         => false,
                'reserved'   => [],
                'taken'      => $seriales,
                'expires_at' => $expiresAt->toDateTimeString(),
            ];
        }

        $reserved = [];
        $taken    = [];

        DB::transaction(function () use (
            $codigoProducto,
            $seriales,
            $token,
            $userId,
            $expiresAt,
            &$reserved,
            &$taken
        ) {
            foreach ($seriales as $ns) {
                if (!$this->serialExistsForProduct($codigoProducto, $ns)) {
                    $taken[] = $ns;
                    continue;
                }

                $row = SerieReserva::query()
                    ->where('codigo_producto', $codigoProducto)
                    ->where('numero_serie', $ns)
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    if ($row->token === $token && $row->estado === 'reservado') {
                        $row->expires_at = $expiresAt;
                        $row->user_id    = $userId;
                        $row->save();
                        $reserved[] = $ns;
                        continue;
                    }

                    $isActiveReserve = ($row->estado === 'reservado')
                        && (empty($row->expires_at) || $row->expires_at->gt(now()));

                    if ($row->estado === 'asignado' || $isActiveReserve) {
                        $taken[] = $ns;
                        continue;
                    }

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
        });

        $this->refreshProductStockTotals($codigoProducto);

        return [
            'ok'         => empty($taken),
            'reserved'   => $reserved,
            'taken'      => $taken,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    public function releaseSeries(string $token, ?array $seriales = null, ?int $codigoProducto = null): int
    {
        $this->cleanupExpiredSerieReservas();

        $token = trim((string) $token);
        if ($token === '') {
            return 0;
        }

        $q = SerieReserva::query()
            ->where('token', $token)
            ->where('estado', 'reservado');

        if ($codigoProducto !== null) {
            $q->where('codigo_producto', (int) $codigoProducto);
        }

        if (is_array($seriales) && count($seriales)) {
            $seriales = array_values(array_unique(array_filter(
                array_map(fn($s) => trim((string) $s), $seriales),
                fn($s) => $s !== ''
            )));

            if (!empty($seriales)) {
                $q->whereIn('numero_serie', $seriales);
            }
        }

        $affectedCodes = [];
        try {
            $affectedCodes = $q->pluck('codigo_producto')->filter()->unique()->values()->all();
        } catch (\Throwable $e) {
            $affectedCodes = [];
        }

        try {
            $deleted = (int) $q->delete();
        } catch (\Throwable $e) {
            return 0;
        }

        foreach ($affectedCodes as $codigo) {
            $this->refreshProductStockTotals((int) $codigo);
        }

        return $deleted;
    }

    public function finalizeSeries(string $token, string $sourceType, int $sourceId): void
    {
        $token = trim((string) $token);
        if ($token === '') {
            return;
        }

        $affectedCodes = SerieReserva::query()
            ->where('token', $token)
            ->where('estado', 'reservado')
            ->pluck('codigo_producto')
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            SerieReserva::query()
                ->where('token', $token)
                ->where('estado', 'reservado')
                ->update([
                    'estado'      => 'asignado',
                    'source_type' => $sourceType,
                    'source_id'   => $sourceId,
                    'assigned_at' => now(),
                    'expires_at'  => null,
                    'updated_at'  => now(),
                ]);
        } catch (\Throwable $e) {
            // noop
        }

        foreach ($affectedCodes as $codigo) {
            $this->refreshProductStockTotals((int) $codigo);
        }
    }

    public function deleteAssignedSeriesBySource(string $sourceType, int $sourceId): int
    {
        try {
            $affectedCodes = SerieReserva::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('estado', 'asignado')
                ->pluck('codigo_producto')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $deleted = (int) SerieReserva::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('estado', 'asignado')
                ->delete();

            foreach ($affectedCodes as $codigo) {
                $this->refreshProductStockTotals((int) $codigo);
            }

            return $deleted;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function serialesBloqueadosPorOtros(int $codigoProducto, ?string $token = null): array
    {
        return $this->serialesNoDisponibles($codigoProducto, $token);
    }

    public function isSerialType(?string $tipo): bool
    {
        $t = strtolower(trim((string) $tipo));

        if ($t === 'piezas') {
            return false;
        }

        $compact = str_replace([' ', '.', '-', '_'], '', $t);

        if (Str::contains($compact, ['serie', 'serial', 'numerodeserie'])) {
            return true;
        }

        return preg_match('/\bns\b|n\/s/i', (string) $tipo) === 1;
    }

    public function productHasSerial(int $codigoProducto): bool
    {
        if ($codigoProducto <= 0) {
            return false;
        }

        $latestTipo = null;

        try {
            $latestTipo = Inventario::where('codigo_producto', $codigoProducto)
                ->orderByDesc('fecha_entrada')
                ->orderByDesc('id')
                ->value('tipo_control');
        } catch (\Throwable $e) {
            $latestTipo = null;
        }

        if ($this->isSerialType($latestTipo)) {
            return true;
        }

        try {
            $hasInvSerial = Inventario::where('codigo_producto', $codigoProducto)
                ->whereNotNull('numero_serie')
                ->where('numero_serie', '!=', '')
                ->exists();

            if ($hasInvSerial) {
                return true;
            }
        } catch (\Throwable $e) {
            // noop
        }

        try {
            $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');

            if ($invIds->isNotEmpty()) {
                return NumeroSerie::whereIn('inventario_id', $invIds)->exists();
            }
        } catch (\Throwable $e) {
            // noop
        }

        return false;
    }

    protected function getPhysicalSerialsForProduct(int $codigoProducto): array
    {
        $serials = [];

        try {
            $fromInv = Inventario::where('codigo_producto', $codigoProducto)
                ->whereNotNull('numero_serie')
                ->where('numero_serie', '!=', '')
                ->orderBy('id')
                ->pluck('numero_serie')
                ->toArray();

            $serials = array_merge($serials, $fromInv);
        } catch (\Throwable $e) {
            // noop
        }

        try {
            $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');

            if ($invIds->isNotEmpty()) {
                $fromTable = NumeroSerie::whereIn('inventario_id', $invIds)
                    ->orderBy('id')
                    ->pluck('numero_serie')
                    ->toArray();

                $serials = array_merge($serials, $fromTable);
            }
        } catch (\Throwable $e) {
            // noop
        }

        $clean = array_values(array_unique(array_filter(array_map(
            fn($s) => trim((string) $s),
            $serials
        ), fn($s) => $s !== '')));

        return $clean;
    }

    protected function availableSeriesOrdered(int $codigoProducto, ?string $token = null, array $exclude = []): array
    {
        $this->cleanupExpiredSerieReservas();

        if ($codigoProducto <= 0) {
            return [];
        }

        $blocked = $this->serialesNoDisponibles($codigoProducto, $token);
        $blocked = array_merge($blocked, array_map(fn($x) => trim((string) $x), $exclude));
        $blockedSet = array_flip(array_filter($blocked, fn($x) => $x !== ''));

        $all = $this->getPhysicalSerialsForProduct($codigoProducto);

        return array_values(array_filter($all, fn($ns) => !isset($blockedSet[(string) $ns])));
    }

    public function peekSeriesAll(int $codigoProducto, ?string $token = null): array
    {
        return $this->availableSeriesOrdered($codigoProducto, $token);
    }

    public function peekAvailableSerials(int $codigoProducto, float $cantidad, ?string $token = null): array
    {
        $needed = (int) ceil($cantidad);

        if ($needed <= 0) {
            return [];
        }

        return array_slice($this->peekSeriesAll($codigoProducto, $token), 0, $needed);
    }

    protected function serialExistsForProduct(int $codigoProducto, string $ns): bool
    {
        $ns = trim($ns);

        if ($codigoProducto <= 0 || $ns === '') {
            return false;
        }

        try {
            if (Inventario::where('codigo_producto', $codigoProducto)->where('numero_serie', $ns)->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            // noop
        }

        try {
            $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');

            if ($invIds->isEmpty()) {
                return false;
            }

            return NumeroSerie::where('numero_serie', $ns)
                ->whereIn('inventario_id', $invIds)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function physicalQuantityForNonSerialProduct(int $codigoProducto): float
    {
        try {
            $rows = Inventario::where('codigo_producto', $codigoProducto)->get();

            if ($rows->isEmpty()) {
                $producto = Producto::find($codigoProducto);
                return max((float) ($producto->stock_total ?? 0), 0);
            }

            $sum = 0.0;
            foreach ($rows as $row) {
                $sum += $this->rowQuantityFromInventario($row);
            }

            return max($sum, 0);
        } catch (\Throwable $e) {
            $producto = Producto::find($codigoProducto);
            return max((float) ($producto->stock_total ?? 0), 0);
        }
    }

    protected function rowQuantityFromInventario($row): float
    {
        $get = fn(string $k, $default = null) => isset($row->{$k}) ? $row->{$k} : $default;

        $paquetesRest = $get('paquetes_restantes');
        $piezasXPack  = $get('piezas_por_paquete');
        $piezasSueltas = $get('piezas_sueltas');

        if ($paquetesRest !== null || $piezasXPack !== null || $piezasSueltas !== null) {
            $packs   = max((float) ($paquetesRest ?? 0), 0);
            $ppp     = max((float) ($piezasXPack ?? 0), 0);
            $sueltas = max((float) ($piezasSueltas ?? 0), 0);

            return max(($packs * $ppp) + $sueltas, 0);
        }

        foreach (
            [
                'stock_actual',
                'cantidad_disponible',
                'cantidad_actual',
                'existencia',
                'stock_total',
                'cantidad',
            ] as $col
        ) {
            $val = $get($col);
            if ($val !== null && $val !== '') {
                return max((float) $val, 0);
            }
        }

        $cantidadIngresada = $get('cantidad_ingresada');
        if ($cantidadIngresada !== null && $cantidadIngresada !== '') {
            $qty  = max((float) $cantidadIngresada, 0);
            $tipo = strtolower(trim((string) ($get('tipo_control', '') ?? '')));
            $ppp  = max((float) ($get('piezas_por_paquete', 0) ?? 0), 0);
            $slt  = max((float) ($get('piezas_sueltas', 0) ?? 0), 0);

            if ($ppp > 0 && preg_match('/paquete|paquetes|caja|cajas/', $tipo)) {
                return max(($qty * $ppp) + $slt, 0);
            }

            return $qty;
        }

        return 0.0;
    }

    protected function currentAssignedNonSerialQty(int $codigoProducto): float
    {
        if ($codigoProducto <= 0 || $this->productHasSerial($codigoProducto)) {
            return 0.0;
        }

        try {
            $sum = DetalleOrdenProducto::query()
                ->where('codigo_producto', $codigoProducto)
                ->whereHas('orden', function ($q) {
                    $q->where(function ($w) {
                        $w->whereNull('estado')
                            ->orWhereNotIn('estado', [
                                'Cancelado',
                                'Cancelada',
                                'cancelado',
                                'cancelada',
                            ]);
                    });
                })
                ->sum('cantidad');

            return max((float) $sum, 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public function calculateAvailableForProduct(int $codigoProducto, ?string $token = null): int
    {
        if ($codigoProducto <= 0) {
            return 0;
        }

        if ($this->productHasSerial($codigoProducto)) {
            return max(count($this->peekSeriesAll($codigoProducto, $token)), 0);
        }

        $physical = $this->physicalQuantityForNonSerialProduct($codigoProducto);
        $assigned = $this->currentAssignedNonSerialQty($codigoProducto);

        return max((int) floor(max($physical - $assigned, 0)), 0);
    }

    public function refreshProductStockTotals(int $codigoProducto): int
    {
        if ($codigoProducto <= 0) {
            return 0;
        }

        $available = $this->calculateAvailableForProduct($codigoProducto);

        try {
            $producto = Producto::find($codigoProducto);
            if (!$producto) {
                return $available;
            }

            if ($this->productoHasColumn('stock_total')) {
                $producto->stock_total = $available;
            }

            $producto->save();
        } catch (\Throwable $e) {
            // noop
        }

        return $available;
    }

    public function refreshProductsTouchedByOrder(int $ordenId): void
    {
        try {
            $codigos = DetalleOrdenProducto::where('id_orden_servicio', $ordenId)
                ->pluck('codigo_producto')
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach ($codigos as $codigo) {
                $this->refreshProductStockTotals((int) $codigo);
            }
        } catch (\Throwable $e) {
            // noop
        }
    }

    public function preflightStockCheck(array $items, ?string $token = null, ?string $sourceType = null, ?int $sourceId = null): array
    {
        $shortages = [];
        $annotated = [];

        foreach ($items as $it) {
            $codigo = (int) ($it['codigo_producto'] ?? 0);
            $qty    = (int) ceil($this->quantityFrom($it));

            $disponible = $codigo > 0
                ? $this->calculateAvailableForProduct($codigo, $token)
                : 0;

            $annot = $it;
            $annot['stock_max']        = $disponible;
            $annot['stock_disponible'] = $disponible;
            $annot['stock']            = $disponible;
            $annot['disponible']       = $disponible;
            $annot['faltante']         = 0;
            $annot['sin_stock']        = false;

            if ($codigo <= 0 || $qty <= 0) {
                $annotated[] = $annot;
                continue;
            }

            if ($this->productHasSerial($codigo)) {
                $preferidos = array_values(array_unique(array_filter(
                    array_map(fn($x) => trim((string) $x), (array) ($it['ns_asignados'] ?? [])),
                    fn($x) => $x !== '')
                ));

                if (!empty($preferidos)) {
                    $availableSet = array_flip($this->peekSeriesAll($codigo, $token));
                    $missing = array_values(array_filter($preferidos, fn($ns) => !isset($availableSet[(string) $ns])));

                    if (!empty($missing)) {
                        $annot['faltante']  = count($missing);
                        $annot['sin_stock'] = true;

                        $shortages[] = [
                            'codigo_producto' => $codigo,
                            'requerido'       => $qty,
                            'disponible'      => $disponible,
                            'faltante'        => max(count($missing), 0),
                            'missing_serials' => $missing,
                        ];

                        $annotated[] = $annot;
                        continue;
                    }
                }
            }

            if ($qty > $disponible) {
                $annot['faltante']  = max($qty - $disponible, 0);
                $annot['sin_stock'] = true;

                $shortages[] = [
                    'codigo_producto' => $codigo,
                    'requerido'       => $qty,
                    'disponible'      => $disponible,
                    'faltante'        => max($qty - $disponible, 0),
                ];
            }

            $annotated[] = $annot;
        }

        return [
            'ok'        => empty($shortages),
            'shortages' => $shortages,
            'annotated' => $annotated,
        ];
    }

    public function failIfShortage(array $check): void
    {
        if (!empty($check['ok'])) {
            return;
        }

        $shortages = array_values($check['shortages'] ?? []);

        throw new HttpResponseException(response()->json([
            'message'          => 'No hay stock suficiente para uno o más productos.',
            'errors'           => [
                'productos' => ['Stock insuficiente en uno o más productos.'],
            ],
            'shortages'        => $shortages,
            'productos_preview'=> array_values($check['annotated'] ?? []),
        ], 422));
    }

    public function consumeAndPrepareLineItems(array $items, ?string $token = null): array
    {
        $final = [];

        foreach ($items as $it) {
            $codigo   = !empty($it['codigo_producto']) ? (int) $it['codigo_producto'] : null;
            $qty      = $this->quantityFrom($it);
            $desc     = $it['descripcion'] ?? null;
            $serials  = [];

            if ($codigo && $qty > 0 && $this->productHasSerial($codigo)) {
                $preferidos = array_values(array_filter((array) ($it['ns_asignados'] ?? []), fn($x) => trim((string) $x) !== ''));
                $serials    = $this->allocateSerialsAndConsume($codigo, $qty, $preferidos, $token);

                if (!empty($serials)) {
                    $desc = trim(($desc ? $desc . "\n" : '') . 'NS: ' . implode(', ', $serials));
                }
            }

            // ✅ no serializados: NO se consume inventario físico
            $final[] = [
                'codigo_producto' => $codigo,
                'descripcion'     => $desc,
                'nombre_producto' => $it['nombre_producto'] ?? null,
                'cantidad'        => $qty,
                'precio'          => $this->unitPriceFrom($it),
                'ns_asignados'    => $serials,
                'has_serial'      => $codigo ? $this->productHasSerial($codigo) : false,
            ];
        }

        return $final;
    }

    public function prepareLineItemsWithSerials(array $items, ?string $token = null): array
    {
        $final = [];

        foreach ($items as $it) {
            $codigo  = !empty($it['codigo_producto']) ? (int) $it['codigo_producto'] : null;
            $qty     = $this->quantityFrom($it);
            $desc    = $it['descripcion'] ?? null;
            $serials = array_values(array_filter((array) ($it['ns_asignados'] ?? []), fn($x) => trim((string) $x) !== ''));

            if ($codigo && $qty > 0 && empty($serials) && $this->productHasSerial($codigo)) {
                $serials = $this->peekAvailableSerials($codigo, $qty, $token);
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
                'has_serial'      => $codigo ? $this->productHasSerial($codigo) : false,
            ];
        }

        return $final;
    }

    public function allocateSerialsAndConsume(
        int $codigoProducto,
        float $cantidad,
        array $preferidos = [],
        ?string $token = null
    ): array {
        $needed = (int) ceil($cantidad);

        if ($needed <= 0) {
            return [];
        }

        // ✅ no serializados: no tocar inventario
        if (!$this->productHasSerial($codigoProducto)) {
            return [];
        }

        $preferidos = array_values(array_unique(array_filter(
            array_map(fn($s) => trim((string) $s), $preferidos),
            fn($s) => $s !== '')
        ));

        $collected = [];

        if (!empty($preferidos) && $token) {
            $res = $this->reserveSeries(
                $codigoProducto,
                $preferidos,
                $token,
                auth()->id(),
                30
            );

            if (!empty($res['taken'])) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Algunos números de serie ya no están disponibles.',
                    'errors'  => [
                        'productos' => ['N/S no disponibles: ' . implode(', ', $res['taken'])],
                    ],
                    'missing_serials' => array_values($res['taken']),
                ], 422));
            }
        }

        if (!empty($preferidos)) {
            $tomados   = $this->allocateSpecificSerials($codigoProducto, $preferidos, $needed, $token);
            $collected = array_merge($collected, $tomados);
            $needed   -= count($tomados);
        }

        if ($needed > 0) {
            $auto = array_slice($this->availableSeriesOrdered($codigoProducto, $token, $collected), 0, $needed);

            if (!empty($auto) && $token) {
                $res = $this->reserveSeries(
                    $codigoProducto,
                    $auto,
                    $token,
                    auth()->id(),
                    30
                );

                if (!empty($res['taken'])) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Algunos números de serie ya no están disponibles.',
                        'errors'  => [
                            'productos' => ['N/S no disponibles: ' . implode(', ', $res['taken'])],
                        ],
                        'missing_serials' => array_values($res['taken']),
                    ], 422));
                }

                $auto = array_values($res['reserved'] ?? $auto);
            }

            $collected = array_merge($collected, $auto);
            $needed   -= count($auto);
        }

        if ($needed > 0) {
            throw new HttpResponseException(response()->json([
                'message' => "No hay números de serie suficientes para el producto {$codigoProducto}.",
                'errors'  => [
                    'productos' => ["Faltan {$needed} número(s) de serie del producto {$codigoProducto}."],
                ],
                'shortages' => [[
                    'codigo_producto' => $codigoProducto,
                    'requerido'       => (int) ceil($cantidad),
                    'disponible'      => $this->calculateAvailableForProduct($codigoProducto, $token),
                    'faltante'        => $needed,
                ]],
            ], 422));
        }

        return array_values(array_unique($collected));
    }

    public function allocateSpecificSerials(
        int $codigoProducto,
        array $preferidos,
        int $max,
        ?string $token = null
    ): array {
        if ($max <= 0) {
            return [];
        }

        $preferidos = array_values(array_unique(array_filter(
            array_map(fn($s) => trim((string) $s), $preferidos),
            fn($s) => $s !== '')
        ));

        if (empty($preferidos)) {
            return [];
        }

        $availableSet = array_flip($this->availableSeriesOrdered($codigoProducto, $token));
        $consumidos   = [];

        foreach ($preferidos as $ns) {
            if (count($consumidos) >= $max) {
                break;
            }

            if (!isset($availableSet[$ns])) {
                continue;
            }

            if (!$this->serialExistsForProduct($codigoProducto, $ns)) {
                continue;
            }

            $consumidos[] = $ns;
        }

        return $consumidos;
    }

    public function consumeSerialFIFOFromInventario(int $codigoProducto, int $needed, ?string $token = null): array
    {
        if ($needed <= 0) {
            return [];
        }

        $blockedSet = array_flip($this->serialesNoDisponibles($codigoProducto, $token));

        $serials = Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->where('numero_serie', '!=', '')
            ->orderBy('id')
            ->pluck('numero_serie')
            ->map(fn($x) => trim((string) $x))
            ->filter(fn($x) => $x !== '' && !isset($blockedSet[$x]))
            ->values()
            ->all();

        $selected = array_slice(array_values(array_unique($serials)), 0, $needed);

        if (!empty($selected) && $token) {
            $res = $this->reserveSeries($codigoProducto, $selected, $token, auth()->id(), 30);
            if (!empty($res['taken'])) {
                $selected = array_values(array_diff($selected, $res['taken']));
            } else {
                $selected = array_values($res['reserved'] ?? $selected);
            }
        }

        return $selected;
    }

    public function consumeSerialFIFOFromNumeroSerie(int $codigoProducto, int $needed, ?string $token = null): array
    {
        if ($needed <= 0) {
            return [];
        }

        $blockedSet = array_flip($this->serialesNoDisponibles($codigoProducto, $token));

        $invIds = Inventario::where('codigo_producto', $codigoProducto)->pluck('id');
        if ($invIds->isEmpty()) {
            return [];
        }

        $serials = NumeroSerie::whereIn('inventario_id', $invIds)
            ->orderBy('id')
            ->pluck('numero_serie')
            ->map(fn($x) => trim((string) $x))
            ->filter(fn($x) => $x !== '' && !isset($blockedSet[$x]))
            ->values()
            ->all();

        $selected = array_slice(array_values(array_unique($serials)), 0, $needed);

        if (!empty($selected) && $token) {
            $res = $this->reserveSeries($codigoProducto, $selected, $token, auth()->id(), 30);
            if (!empty($res['taken'])) {
                $selected = array_values(array_diff($selected, $res['taken']));
            } else {
                $selected = array_values($res['reserved'] ?? $selected);
            }
        }

        return $selected;
    }

    /* =========================================================
     |  Crédito
     * ========================================================= */
    public function checkCreditoVencido(?CreditoCliente $credito): array
    {
        if (!$credito) {
            return [
                'expired'        => false,
                'reason'         => null,
                'estatus'        => null,
                'fecha_limite'   => null,
                'dias_restantes' => null,
            ];
        }

        $status = strtolower(trim((string) ($credito->estatus ?? $credito->estado ?? 'activo')));
        $fecha = $credito->fecha_limite
            ?? $credito->fecha_vencimiento
            ?? $credito->fecha_asignacion
            ?? null;

        $fechaLimite = null;
        $diasRestantes = null;

        if (!empty($fecha)) {
            try {
                $fechaLimite = Carbon::parse($fecha);
                $diasRestantes = (int) Carbon::today()->diffInDays($fechaLimite, false);
            } catch (\Throwable $e) {
                $fechaLimite = null;
                $diasRestantes = null;
            }
        }

        if (in_array($status, ['vencido', 'bloqueado', 'inactivo'], true)) {
            return [
                'expired'        => true,
                'reason'         => $status,
                'estatus'        => $status,
                'fecha_limite'   => $fechaLimite?->toDateString(),
                'dias_restantes' => $diasRestantes,
            ];
        }

        if (!empty($fecha)) {
            try {
                if ($fechaLimite && $fechaLimite->lte(Carbon::today())) {
                    return [
                        'expired'        => true,
                        'reason'         => 'fecha_limite',
                        'estatus'        => 'vencido',
                        'fecha_limite'   => $fechaLimite->toDateString(),
                        'dias_restantes' => $diasRestantes,
                    ];
                }
            } catch (\Throwable $e) {
                // noop
            }
        }

        return [
            'expired'        => false,
            'reason'         => null,
            'estatus'        => 'activo',
            'fecha_limite'   => $fechaLimite?->toDateString(),
            'dias_restantes' => $diasRestantes,
        ];
    }
}
