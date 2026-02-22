<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CargaRapidaProductosController extends Controller
{
    public function index()
    {
        return view('vistas-gerente.inventario-gerente.carga_rapida_productos', [
            'preview' => false,
        ]);
    }

    public function procesar(Request $request)
    {
        // ===== Paso 2: Confirmación =====
        if ($request->has('confirm') || $request->has('payload')) {
            $request->validate([
                'modo_carga' => ['required', Rule::in(['solo_productos','con_inventario'])],
                'payload'    => ['required','string','min:2'],
            ]);

            $rows = json_decode($request->payload, true);
            if (!is_array($rows)) {
                return back()
                    ->with('error', 'Payload inválido. Vuelve a generar la vista previa.')
                    ->withInput();
            }

            [$ok, $skip] = $this->confirmarInsercion(
                $rows,
                $request->modo_carga === 'con_inventario'
            );

            return redirect()
                ->route('cargaRapidaProd.index')
                ->with('success', "Carga rápida completada. Procesados: {$ok}. Saltados: {$skip}.");
        }

        // ===== Paso 1: Preview =====
        $request->validate([
            'archivo'    => ['required','file','mimes:xlsx,csv,txt'],
            'modo_carga' => ['required', Rule::in(['solo_productos','con_inventario'])],
        ]);

        try {
            $ext = strtolower($request->file('archivo')->getClientOriginalExtension());
            if ($ext === 'xlsx') {
                $sheet = IOFactory::load($request->file('archivo')->getRealPath())->getActiveSheet();
                $rows  = $sheet->toArray(null, true, true, true); // índices A,B,C...
            } else {
                $raw = array_map('str_getcsv', file($request->file('archivo')->getRealPath()));
                $rows = [];
                $i = 1;
                foreach ($raw as $arr) {
                    $row = [];
                    $col = 'A';
                    foreach ($arr as $v) {
                        $row[$col++] = $v;
                    }
                    $rows[$i++] = $row;
                }
            }
        } catch (\Throwable $e) {
            return back()
                ->with('error','No se pudo leer el archivo: '.$e->getMessage())
                ->withInput();
        }

        if (empty($rows)) {
            return view('vistas-gerente.inventario-gerente.carga_rapida_productos', [
                'preview'    => true,
                'items'      => [],
                'stats'      => ['total'=>0,'duplicados'=>0,'aceptables'=>0,'vacios'=>0],
                'modo_carga' => $request->modo_carga,
            ]);
        }

        // Detectar fila de encabezados
        $headerRow = $this->detectarFilaEncabezados($rows, 20);
        $headers   = $this->normalizaHeaders($rows[$headerRow] ?? []);
        for ($i = 1; $i <= $headerRow; $i++) {
            unset($rows[$i]);
        }

        // Mapear columnas
        $idx = [
            'descripcion'        => $this->findHeader($headers, ['descripcion','descripción','producto','concepto']),
            'clave_prodserv'     => $this->findHeader($headers, ['clave prod/serv','clave prodserv','clave producto','c_claveprodserv']),
            // ⚠️ no se guarda en productos (solo defaults de unidad)
            'clave_unidad'       => $this->findHeader($headers, ['clave unidad','c_claveunidad','claveunidad']),
            'unidad'             => $this->findHeader($headers, ['unidad','u']),
            // 👇 si viene en el excel la leemos, pero YA NO se guarda en productos
            'unidad_desc'        => $this->findHeader($headers, ['unidad desc','unidad descripción','unidad descripcion']),
            'valor_unitario'     => $this->findHeader($headers, ['valor unitario','precio unitario','p.u.','pu','precio']),
            // ⚠️ ya NO existe en productos: solo lo usamos para sugerir numero_parte si viene en el excel
            'num_identificacion' => $this->findHeader($headers, ['num identificacion','núm. identificación','numero identificacion']),
            'cantidad'           => $this->findHeader($headers, ['cantidad','cant','qty']),
            'piezas_por_paquete' => $this->findHeader($headers, ['piezas por paquete','pzs por paquete']),
            'nombre_emisor'      => $this->findNombreEmisorCol($headers),
            'rfc'                => $this->findRfcEmisorCol($headers),
            'categoria'          => $this->findHeader($headers, ['categoria','categoría']),
        ];

        // Heurísticas si falta algo clave
        if (!$idx['descripcion'])     $idx['descripcion']    = $this->guessDescripcionCol($rows);
        if (!$idx['cantidad'])        $idx['cantidad']       = $this->guessCantidadCol($rows);
        if (!$idx['valor_unitario'])  $idx['valor_unitario'] = $this->guessPrecioCol($rows);

        $previewItems = [];
        $stats = ['total'=>0,'duplicados'=>0,'aceptables'=>0,'vacios'=>0];

        foreach ($rows as $r) {
            $stats['total']++;

            $desc = trim((string)$this->val($r, $idx['descripcion']));
            if ($desc === '') {
                $stats['vacios']++;
                continue;
            }

            $claveProd   = $this->soloDigitos($this->val($r, $idx['clave_prodserv']));
            $claveUnidad = $this->toUpper($this->val($r, $idx['clave_unidad'])); // solo defaults

            $unidad     = $this->trimNull($this->val($r, $idx['unidad']));
            $unidadDesc = $this->trimNull($this->val($r, $idx['unidad_desc'])); // NO se guarda en productos
            $unidad     = $this->unidadConDefaults($claveUnidad, $unidad, $unidadDesc);

            $numIdExcel    = $this->toUpper($this->val($r, $idx['num_identificacion'])); // solo para sugerir numero_parte
            $cantidad      = $this->parseInt($this->val($r, $idx['cantidad']));
            $ppp           = max(1, (int)$this->parseInt($this->val($r, $idx['piezas_por_paquete'])));
            [$valorUnit, $redondeado] = $this->precioMXN($this->val($r, $idx['valor_unitario']));
            $categoria     = $this->categoriaInferida(['categoria' => $this->val($r, $idx['categoria'])]);

            $proveedorNombre = $this->normalizeName($this->val($r, $idx['nombre_emisor']));
            $rfc             = $this->toUpper($this->val($r, $idx['rfc']));

            // ✅ numero_parte sugerido (prioridad: num_identificacion del excel; si no, SKU)
            $numeroParteSugerido = $numIdExcel ?: ('SKU-'.strtoupper(Str::random(8)));

            // Detectar duplicados (por numero_parte o por nombre+clave_prodserv)
            [$dup, $dupReason, $existingId] = $this->esDuplicadoProducto(
                $numeroParteSugerido,
                $desc,
                $claveProd
            );

            if ($dup) $stats['duplicados']++;
            else $stats['aceptables']++;

            $previewItems[] = [
                'include'            => true,
                'tipo_control'       => 'PIEZAS',
                'cantidad'           => max(0, $cantidad),
                'piezas_por_paquete' => $ppp,
                'valor_unitario'     => $valorUnit,
                'valor_redondeado'   => $redondeado,

                'descripcion'        => $desc,
                'clave_prodserv'     => $claveProd,
                'clave_unidad'       => $claveUnidad, // solo defaults
                'unidad'             => $unidad,

                // 👇 se mantiene en payload por si tu vista lo muestra, pero NO se guardará en productos
                'unidad_desc'        => $unidadDesc,

                'num_identificacion' => $numIdExcel,
                'categoria'          => $categoria,

                'numero_parte'       => $numeroParteSugerido,
                'duplicado'          => $dup,
                'dup_reason'         => $dupReason,
                'existing_id'        => $existingId,

                'proveedor_nombre'   => $proveedorNombre,
                'proveedor_rfc'      => $rfc,

                'series'             => [],
            ];
        }

        return view('vistas-gerente.inventario-gerente.carga_rapida_productos', [
            'preview'    => true,
            'items'      => $previewItems,
            'stats'      => $stats,
            'modo_carga' => $request->modo_carga,
        ]);
    }

    /* ===================== Confirmación ===================== */

    private function confirmarInsercion(array $rows, bool $conInventario): array
    {
        $ok   = 0;
        $skip = 0;

        foreach ($rows as $i) {

            if (empty($i['include'])) {
                $skip++;
                continue;
            }

            $descripcion = trim((string)($i['descripcion'] ?? ''));
            if ($descripcion === '') {
                $skip++;
                continue;
            }

            $claveProd = $i['clave_prodserv'] ?? null;

            // ✅ clave_unidad no existe en productos (solo defaults para unidad)
            $claveUnidad = $i['clave_unidad'] ?? null;
            $unidad      = $i['unidad'] ?? null;

            // 👇 si viene unidad_desc del excel/payload, la usamos como fallback para unidad
            $unidadDesc  = $i['unidad_desc'] ?? null;

            $unidad = $this->unidadConDefaults($claveUnidad, $unidad, $unidadDesc);

            $categoria = $this->categoriaInferida(['categoria' => $i['categoria'] ?? null]);

            // numero_parte sugerido (ya viene construido desde preview)
            $numeroParte = strtoupper((string)($i['numero_parte'] ?? 'SKU-'.Str::random(8)));

            // ===== Producto (deduplicación) =====
            $producto = null;

            if (!empty($i['existing_id'])) {
                $producto = Producto::find($i['existing_id']);
            }

            // ✅ buscar por numero_parte (identificador único)
            if (!$producto && $numeroParte) {
                $producto = Producto::where('numero_parte', $numeroParte)->first();
            }

            if (!$producto) {
                $producto = Producto::where('nombre', $descripcion)
                    ->when($claveProd, fn($q) => $q->where('clave_prodserv', $claveProd))
                    ->first();
            }

            if ($producto) {
                // === NO sobreescribir campos existentes ===
                $producto->nombre         = $producto->nombre ?: $descripcion;
                $producto->categoria      = $producto->categoria ?: $categoria;
                $producto->clave_prodserv = $producto->clave_prodserv ?: $claveProd;
                $producto->unidad         = $producto->unidad ?: $unidad;
                $producto->activo         = true;
                $producto->save();
            } else {
                // Crear nuevo (sin unidad_desc / sin num_identificacion / sin clave_unidad)
                $numeroParte = $this->uniqueNumeroParte($numeroParte);

                $producto = Producto::create([
                    'nombre'               => $descripcion,
                    'numero_parte'         => $numeroParte,
                    'categoria'            => $categoria,
                    'clave_prodserv'       => $claveProd,
                    'unidad'               => $unidad,
                    'descripcion'          => $descripcion,
                    'activo'               => true,
                    'stock_total'          => 0,
                    'stock_paquetes'       => 0,
                    'stock_piezas_sueltas' => 0,
                ]);
            }

            // ===== Proveedor (Emisor) =====
            $provNombre = $this->normalizeName($i['proveedor_nombre'] ?? null);
            $provRfc    = $this->toUpper($i['proveedor_rfc'] ?? null);
            $proveedor  = null;

            if ($provRfc) {
                $proveedor = Proveedor::whereRaw('UPPER(rfc)=?', [$provRfc])->first();
                if ($proveedor) {
                    $nombreActual = $this->normalizeName($proveedor->nombre ?? '');
                    if ($provNombre && ($nombreActual === '' || $nombreActual === $provRfc)) {
                        $proveedor->nombre = $provNombre;
                        $proveedor->save();
                    }
                }
            }

            if (!$proveedor && $provNombre) {
                $proveedor = Proveedor::whereRaw('UPPER(TRIM(nombre))=?', [$provNombre])->first();
                if ($proveedor && $provRfc && empty($proveedor->rfc)) {
                    $proveedor->rfc = $provRfc;
                    $proveedor->save();
                }
            }

            if (!$proveedor && ($provNombre || $provRfc)) {
                $proveedor = Proveedor::create([
                    'nombre'    => $provNombre ?: 'SIN NOMBRE',
                    'rfc'       => $provRfc ?: null,
                    'alias'     => '',
                    'direccion' => '',
                    'contacto'  => '',
                    'telefono'  => '',
                    'correo'    => '',
                ]);
            }

            // ===== Inventario (opcional) =====
            if ($conInventario && (int)($i['cantidad'] ?? 0) > 0) {

                $tipo  = in_array(($i['tipo_control'] ?? 'PIEZAS'), ['PIEZAS','PAQUETES','SERIE'])
                    ? $i['tipo_control']
                    : 'PIEZAS';

                $cant  = (int) $i['cantidad'];
                $ppp   = max(1, (int)($i['piezas_por_paquete'] ?? 1));
                [$precio,] = $this->precioMXN($i['valor_unitario'] ?? 0);

                if ($tipo === 'SERIE') {
                    $series = is_array($i['series'] ?? null) ? $i['series'] : [];
                    $series = array_values(array_filter(array_map('trim', $series)));

                    if (count($series) > 0) {
                        foreach ($series as $ns) {
                            Inventario::create([
                                'codigo_producto'    => $producto->codigo_producto,
                                'clave_proveedor'    => $proveedor?->clave_proveedor,
                                'costo'              => $precio,
                                'precio'             => $precio,
                                'tipo_control'       => 'SERIE',
                                'cantidad_ingresada' => 1,
                                'piezas_por_paquete' => null,
                                'paquetes_restantes' => 0,
                                'piezas_sueltas'     => 1,
                                'numero_serie'       => $ns,
                                'fecha_entrada'      => now()->toDateString(),
                                'hora_entrada'       => now()->format('H:i:s'),
                                'fecha_caducidad'    => null,
                            ]);
                        }
                    } else {
                        Inventario::create([
                            'codigo_producto'    => $producto->codigo_producto,
                            'clave_proveedor'    => $proveedor?->clave_proveedor,
                            'costo'              => $precio,
                            'precio'             => $precio,
                            'tipo_control'       => 'SERIE',
                            'cantidad_ingresada' => $cant,
                            'piezas_por_paquete' => null,
                            'paquetes_restantes' => 0,
                            'piezas_sueltas'     => $cant,
                            'numero_serie'       => null,
                            'fecha_entrada'      => now()->toDateString(),
                            'hora_entrada'       => now()->format('H:i:s'),
                            'fecha_caducidad'    => null,
                        ]);
                    }

                } elseif ($tipo === 'PAQUETES') {

                    Inventario::create([
                        'codigo_producto'    => $producto->codigo_producto,
                        'clave_proveedor'    => $proveedor?->clave_proveedor,
                        'costo'              => $precio,
                        'precio'             => $precio,
                        'tipo_control'       => 'PAQUETES',
                        'cantidad_ingresada' => $cant,
                        'piezas_por_paquete' => $ppp,
                        'paquetes_restantes' => $cant,
                        'piezas_sueltas'     => 0,
                        'numero_serie'       => null,
                        'fecha_entrada'      => now()->toDateString(),
                        'hora_entrada'       => now()->format('H:i:s'),
                        'fecha_caducidad'    => null,
                    ]);

                } else { // PIEZAS

                    Inventario::create([
                        'codigo_producto'    => $producto->codigo_producto,
                        'clave_proveedor'    => $proveedor?->clave_proveedor,
                        'costo'              => $precio,
                        'precio'             => $precio,
                        'tipo_control'       => 'PIEZAS',
                        'cantidad_ingresada' => $cant,
                        'piezas_por_paquete' => null,
                        'paquetes_restantes' => 0,
                        'piezas_sueltas'     => $cant,
                        'numero_serie'       => null,
                        'fecha_entrada'      => now()->toDateString(),
                        'hora_entrada'       => now()->format('H:i:s'),
                        'fecha_caducidad'    => null,
                    ]);
                }

                $this->recalcularStockProducto($producto->codigo_producto);
            }

            $ok++;
        }

        return [$ok, $skip];
    }

    /* ===================== Helpers ===================== */

    private function detectarFilaEncabezados(array $rows, int $limit = 20): int
    {
        $limit = min($limit, count($rows));
        $bestRow   = 1;
        $bestScore = -1;

        $must = [
            'descripcion','descripción','concepto','producto','valor',
            'precio','cantidad','clave','unidad','identificacion','emisor','proveedor','rfc'
        ];

        for ($i = 1; $i <= $limit; $i++) {
            if (!isset($rows[$i]) || !is_array($rows[$i])) continue;

            $headers = $this->normalizaHeaders($rows[$i]);
            $score   = 0;

            foreach ($headers as $h) {
                foreach ($must as $t) {
                    if (str_contains($h, mb_strtolower($t,'UTF-8'))) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow   = $i;
            }
        }

        return $bestRow;
    }

    // ✅ duplicado por numero_parte o por nombre+clave_prodserv
    private function esDuplicadoProducto(?string $numeroParte, string $descripcion, ?string $claveProd): array
    {
        if ($numeroParte) {
            $p = Producto::where('numero_parte', strtoupper($numeroParte))->first();
            if ($p) return [true, 'numero_parte coincide', $p->codigo_producto];
        }

        if ($claveProd) {
            $p = Producto::where('nombre', $descripcion)
                ->where('clave_prodserv', $claveProd)
                ->first();
            if ($p) return [true, 'nombre + clave prod/serv', $p->codigo_producto];
        }

        $p = Producto::where('nombre', $descripcion)->first();
        if ($p) return [true, 'nombre coincide', $p->codigo_producto];

        return [false, null, null];
    }

    /**
     * ✅ Devuelve SOLO "unidad" (porque unidad_desc ya no existe en productos).
     * - Si unidad viene vacía y unidad_desc viene con algo, usamos unidad_desc como unidad.
     * - Si aun así falta, usamos defaults por clave_unidad.
     */
    private function unidadConDefaults(?string $clave, ?string $unidad, ?string $unidadDesc = null): string
    {
        $clave = strtoupper(trim((string)$clave));

        $unidad = $unidad ? mb_strtoupper(trim((string)$unidad),'UTF-8') : null;

        // fallback: si "unidad" viene vacía, usar unidad_desc como unidad
        if ((!$unidad || trim($unidad) === '') && $unidadDesc) {
            $unidad = mb_strtoupper(trim((string)$unidadDesc), 'UTF-8');
        }

        $map = [
            'H87' => 'PIEZA',
            'LTR' => 'LITRO',
            'XLT' => 'LOTE',
            'E48' => 'UNIDAD',
        ];

        if (!$unidad && isset($map[$clave])) {
            $unidad = $map[$clave];
        }

        return $unidad ?: 'PIEZA';
    }

    private function precioMXN($v): array
    {
        $raw        = (string)$v;
        $f          = $this->toFloat($raw);
        $redondeado = (bool) preg_match('/\.\d{3,}$/', str_replace(',', '.', $raw));

        return [round($f, 2), $redondeado];
    }

    private function categoriaInferida(array $row): string
    {
        $cat = trim((string)($row['categoria'] ?? ''));
        return mb_strtoupper($cat === '' ? 'GENERAL' : $cat, 'UTF-8');
    }

    private function normalizaHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $col => $val) {
            $h = $this->normHeader($val);
            $h = preg_replace('/^c[\s_\-]+/','', $h);
            $out[$col] = $h;
        }
        return $out;
    }

    private function normHeader($h): string
    {
        $h = trim((string)$h);
        $h = mb_strtolower($h,'UTF-8');
        $h = str_replace(['.','/','\\',':'], ' ', $h);
        $h = preg_replace('/\s+/', ' ', $h);
        return $h;
    }

    private function findHeader(array $headers, array $cands): ?string
    {
        foreach ($headers as $col => $h) {
            foreach ($cands as $c) {
                if (str_contains($h, $this->normHeader($c))) {
                    return $col;
                }
            }
        }
        return null;
    }

    private function findNombreEmisorCol(array $headers): ?string
    {
        foreach ($headers as $col => $h) {
            $h = $this->normHeader($h);
            if (str_contains($h, 'rfc')) continue;
            if (str_contains($h, 'nombre') && (str_contains($h, 'emisor') || str_contains($h, 'proveedor'))) {
                return $col;
            }
        }

        foreach ($headers as $col => $h) {
            $h = $this->normHeader($h);
            if (!str_contains($h, 'rfc') && str_contains($h, 'proveedor')) {
                return $col;
            }
        }

        return null;
    }

    private function findRfcEmisorCol(array $headers): ?string
    {
        foreach ($headers as $col => $h) {
            $h = $this->normHeader($h);
            if (str_contains($h, 'rfc')) {
                return $col;
            }
        }
        return null;
    }

    private function val(array $row, ?string $col)
    {
        return $col ? ($row[$col] ?? null) : null;
    }

    private function trimNull($v)
    {
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private function toUpper($s): ?string
    {
        if ($s === null) return null;
        return strtoupper(trim((string)$s));
    }

    private function normalizeName($s): ?string
    {
        if ($s === null) return null;
        $s = preg_replace('/\s+/u', ' ', trim((string)$s));
        return $s === '' ? null : mb_strtoupper($s, 'UTF-8');
    }

    private function toFloat($v): float
    {
        if (is_numeric($v)) return (float)$v;

        $v = (string)$v;
        $v = str_replace(['$',' '], '', $v);

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {
            $v = str_replace(',', '', $v);
        } elseif (preg_match('/^\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }

        return (float)$v;
    }

    private function parseInt($v): int
    {
        if ($v === null || $v === '') return 0;
        $v = str_replace([',',' '], '', (string)$v);
        return max(0, (int)round((float)$v));
    }

    private function soloDigitos($v): ?string
    {
        if ($v === null) return null;
        $s = preg_replace('/\D+/', '', (string)$v);
        return $s === '' ? null : $s;
    }

    private function guessDescripcionCol(array $rows): ?string
    {
        $scores = [];
        $n = 0;
        foreach ($rows as $r) {
            $n++;
            if ($n > 20) break;
            foreach ($r as $col => $val) {
                $len = mb_strlen(trim((string)$val), 'UTF-8');
                if ($len >= 12) {
                    $scores[$col] = ($scores[$col] ?? 0) + $len;
                }
            }
        }
        arsort($scores);
        return array_key_first($scores);
    }

    private function guessCantidadCol(array $rows): ?string
    {
        $scores = [];
        $n = 0;
        foreach ($rows as $r) {
            $n++;
            if ($n > 20) break;
            foreach ($r as $col => $val) {
                $v = str_replace([',',' '], '', (string)$val);
                if (preg_match('/^\d+(\.0+)?$/', $v)) {
                    $scores[$col] = ($scores[$col] ?? 0) + 1;
                }
            }
        }
        arsort($scores);
        return array_key_first($scores);
    }

    private function guessPrecioCol(array $rows): ?string
    {
        $scores = [];
        $n = 0;
        foreach ($rows as $r) {
            $n++;
            if ($n > 20) break;
            foreach ($r as $col => $val) {
                $v = str_replace(['$',' '], '', (string)$val);
                if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)
                    || preg_match('/^\d+([.,]\d{1,})$/', $v)) {
                    $scores[$col] = ($scores[$col] ?? 0) + 1;
                }
            }
        }
        arsort($scores);
        return array_key_first($scores);
    }

    private function uniqueNumeroParte(string $base): string
    {
        $base = strtoupper(trim($base));
        $candidate = $base;
        $i = 2;

        while (Producto::where('numero_parte', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
            if ($i > 9999) break;
        }

        return $candidate;
    }

    private function recalcularStockProducto(int $codigo)
    {
        $s = DB::table('inventario')
            ->selectRaw('
                COALESCE(SUM(paquetes_restantes * COALESCE(piezas_por_paquete,1) + COALESCE(piezas_sueltas,0)),0) as total,
                COALESCE(SUM(paquetes_restantes),0) as paquetes,
                COALESCE(SUM(piezas_sueltas),0) as sueltas
            ')
            ->where('codigo_producto', $codigo)
            ->first();

        Producto::where('codigo_producto', $codigo)->update([
            'stock_total'          => (int)($s->total ?? 0),
            'stock_paquetes'       => (int)($s->paquetes ?? 0),
            'stock_piezas_sueltas' => (int)($s->sueltas ?? 0),
        ]);
    }
}
