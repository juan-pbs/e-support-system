<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Importacion\ArchivoImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CargaRapidaInventarioController extends Controller
{
    public function __construct(
        private ArchivoImportService $archivoImportService
    ) {}

    public function index()
    {
        return view('vistas-gerente.inventario-gerente.carga_rapida_inventario', [
            'preview' => false,
            'items'   => [],
            'stats'   => null,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,csv,txt'],
        ]);

        $parsed = $this->archivoImportService->leer(
            $request->file('archivo'),
            $this->aliases()
        );

        $items       = [];
        $seenSeries  = [];

        foreach ($parsed['rows'] as $row) {
            $codigoProducto   = $this->parseInt($row['codigo_producto'] ?? null);
            $numeroParte      = $this->sanitizeText($row['numero_parte'] ?? '');
            $nombreProducto   = $this->sanitizeText($row['producto'] ?? '');
            $producto         = $this->findProduct($codigoProducto, $numeroParte, $nombreProducto);

            $proveedorNombre  = $this->sanitizeText($row['proveedor'] ?? '');
            $proveedorRfc     = $this->normalizeRfc($row['rfc'] ?? '');
            $costo            = $this->parseMoney($row['costo'] ?? 0);
            $precio           = $this->parseMoney($row['precio'] ?? $costo);
            $tipoControl      = $this->normalizeTipoControl($row['tipo_control'] ?? '');
            $cantidad         = $this->parseInt($row['cantidad'] ?? 0);
            $piezasPaquete    = $this->parseInt($row['piezas_por_paquete'] ?? 0);
            $fechaEntrada     = $this->normalizeDate($row['fecha_entrada'] ?? '') ?: now()->toDateString();
            $fechaCaducidad   = $this->normalizeDate($row['fecha_caducidad'] ?? '');
            $series           = $this->parseSeries($row['numeros_serie'] ?? '');

            $estado = 'ACEPTAR';
            $motivo = null;

            if (!$producto) {
                $estado = 'INVALIDO';
                $motivo = 'El producto no existe. Debes importarlo primero al catálogo.';
            }

            if (!in_array($tipoControl, ['PIEZAS', 'PAQUETES', 'SERIE'], true)) {
                $estado = 'INVALIDO';
                $motivo = 'El tipo de control debe ser PIEZAS, PAQUETES o SERIE.';
            }

            if ($tipoControl === 'SERIE') {
                if (count($series) === 0) {
                    $estado = 'INVALIDO';
                    $motivo = 'Para SERIE debes capturar al menos un número de serie.';
                }

                if ($cantidad > 0 && $cantidad !== count($series)) {
                    $estado = 'INVALIDO';
                    $motivo = 'La cantidad no coincide con el número de series capturadas.';
                }

                $duplicadasDb = $this->seriesDuplicadasEnBd($series);
                if (!empty($duplicadasDb)) {
                    $estado = 'INVALIDO';
                    $motivo = 'Estas series ya existen: ' . implode(', ', array_slice($duplicadasDb, 0, 20));
                }

                $duplicadasArchivo = [];
                foreach ($series as $serie) {
                    if (isset($seenSeries[$serie])) {
                        $duplicadasArchivo[] = $serie;
                    }
                }

                if (!empty($duplicadasArchivo)) {
                    $estado = 'INVALIDO';
                    $motivo = 'Estas series vienen repetidas dentro del archivo: ' . implode(', ', array_slice(array_unique($duplicadasArchivo), 0, 20));
                }

                foreach ($series as $serie) {
                    $seenSeries[$serie] = true;
                }

                $cantidad = count($series);
            }

            if (in_array($tipoControl, ['PIEZAS', 'PAQUETES'], true) && $cantidad <= 0) {
                $estado = 'INVALIDO';
                $motivo = 'La cantidad debe ser mayor a cero.';
            }

            if ($tipoControl === 'PAQUETES' && $piezasPaquete <= 0) {
                $estado = 'INVALIDO';
                $motivo = 'Para PAQUETES debes indicar piezas por paquete.';
            }

            $items[] = [
                '_row'               => $row['_row'] ?? null,
                'producto_id'        => $producto?->codigo_producto,
                'codigo_producto'    => $producto?->codigo_producto,
                'numero_parte'       => $producto?->numero_parte ?: $numeroParte,
                'producto_nombre'    => $producto?->nombre ?: $nombreProducto,
                'proveedor'          => $proveedorNombre,
                'rfc'                => $proveedorRfc,
                'costo'              => $costo,
                'precio'             => $precio,
                'tipo_control'       => $tipoControl,
                'cantidad'           => $cantidad,
                'piezas_por_paquete' => $piezasPaquete,
                'numeros_serie'      => $series,
                'fecha_entrada'      => $fechaEntrada,
                'fecha_caducidad'    => $fechaCaducidad,
                'estado'             => $estado,
                'motivo'             => $motivo,
            ];
        }

        $stats = $this->buildStats($items);

        return view('vistas-gerente.inventario-gerente.carga_rapida_inventario', [
            'preview' => true,
            'items'   => $items,
            'stats'   => $stats,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'payload' => ['required', 'string'],
        ]);

        $items = json_decode($request->input('payload'), true);

        if (!is_array($items)) {
            return redirect()
                ->route('inventario.carga_rapida.index')
                ->with('error', 'No se pudo leer el payload de confirmación.');
        }

        $ok     = 0;
        $skip   = 0;
        $errors = [];

        foreach ($items as $item) {
            if (($item['estado'] ?? '') !== 'ACEPTAR') {
                $skip++;
                continue;
            }

            try {
                DB::transaction(function () use ($item) {
                    $producto = Producto::findOrFail($item['producto_id']);
                    $proveedor = $this->resolveProveedor(
                        (string) ($item['proveedor'] ?? ''),
                        (string) ($item['rfc'] ?? '')
                    );

                    $base = [
                        'codigo_producto'   => $producto->codigo_producto,
                        'clave_proveedor'   => $proveedor?->clave_proveedor,
                        'costo'             => (float) ($item['costo'] ?? 0),
                        'precio'            => (float) ($item['precio'] ?? 0),
                        'tipo_control'      => $item['tipo_control'],
                        'fecha_entrada'     => $item['fecha_entrada'] ?: now()->toDateString(),
                        'hora_entrada'      => now()->format('H:i:s'),
                        'fecha_caducidad'   => $item['fecha_caducidad'] ?: null,
                    ];

                    if ($item['tipo_control'] === 'SERIE') {
                        $series = $this->parseSeriesFromPayload($item['numeros_serie'] ?? []);
                        $duplicadas = $this->seriesDuplicadasEnBd($series);

                        if (!empty($duplicadas)) {
                            throw new \RuntimeException(
                                'Estas series ya existen: ' . implode(', ', array_slice($duplicadas, 0, 20))
                            );
                        }

                        foreach ($series as $serie) {
                            Inventario::create(array_merge($base, [
                                'cantidad_ingresada'   => 1,
                                'piezas_por_paquete'   => null,
                                'paquetes_restantes'   => 0,
                                'piezas_sueltas'       => 1,
                                'numero_serie'         => $serie,
                            ]));
                        }
                    } elseif ($item['tipo_control'] === 'PAQUETES') {
                        Inventario::create(array_merge($base, [
                            'cantidad_ingresada'   => (int) $item['cantidad'],
                            'piezas_por_paquete'   => (int) $item['piezas_por_paquete'],
                            'paquetes_restantes'   => (int) $item['cantidad'],
                            'piezas_sueltas'       => 0,
                            'numero_serie'         => null,
                        ]));
                    } else {
                        Inventario::create(array_merge($base, [
                            'cantidad_ingresada'   => (int) $item['cantidad'],
                            'piezas_por_paquete'   => null,
                            'paquetes_restantes'   => 0,
                            'piezas_sueltas'       => (int) $item['cantidad'],
                            'numero_serie'         => null,
                        ]));
                    }

                    $this->recalcularStockProducto((int) $producto->codigo_producto);
                });

                $ok++;
            } catch (\Throwable $e) {
                $skip++;
                $errors[] = 'Fila ' . ($item['_row'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return redirect()
            ->route('inventario.carga_rapida.index')
            ->with('success', "Carga rápida de inventario completada. Procesados: {$ok}. Saltados: {$skip}.")
            ->with('import_errors', $errors);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function aliases(): array
    {
        return [
            'codigo_producto' => [
                'codigo producto',
                'código producto',
                'codigo_producto',
                'id producto'
            ],
            'numero_parte' => [
                'numero parte',
                'número de parte',
                'numero_parte',
                'num parte',
                'sku',
                'no parte'
            ],
            'producto' => [
                'producto',
                'descripcion',
                'descripción',
                'concepto',
                'nombre'
            ],
            'proveedor' => [
                'proveedor',
                'emisor',
                'nombre emisor'
            ],
            'rfc' => [
                'rfc',
                'rfc emisor'
            ],
            'costo' => [
                'costo',
                'costo unitario',
                'valor unitario'
            ],
            'precio' => [
                'precio',
                'precio venta',
                'precio unitario',
                'p u',
                'pu'
            ],
            'tipo_control' => [
                'tipo control',
                'tipo_control',
                'control'
            ],
            'cantidad' => [
                'cantidad',
                'cant',
                'qty'
            ],
            'piezas_por_paquete' => [
                'piezas por paquete',
                'pzs por paquete',
                'piezas paquete'
            ],
            'numeros_serie' => [
                'numeros serie',
                'números de serie',
                'numero serie',
                'número de serie',
                'series'
            ],
            'fecha_entrada' => [
                'fecha entrada',
                'fecha_entrada'
            ],
            'fecha_caducidad' => [
                'fecha caducidad',
                'fecha_caducidad',
                'caducidad'
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function buildStats(array $items): array
    {
        $stats = [
            'total'      => count($items),
            'aceptables' => 0,
            'invalidos'  => 0,
        ];

        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'ACEPTAR') {
                $stats['aceptables']++;
            } else {
                $stats['invalidos']++;
            }
        }

        return $stats;
    }

    private function findProduct(int $codigoProducto, string $numeroParte, string $nombre): ?Producto
    {
        if ($codigoProducto > 0) {
            $p = Producto::where('codigo_producto', $codigoProducto)->first();
            if ($p) {
                return $p;
            }
        }

        if ($numeroParte !== '') {
            $p = Producto::whereRaw('UPPER(TRIM(numero_parte)) = ?', [mb_strtoupper(trim($numeroParte), 'UTF-8')])->first();
            if ($p) {
                return $p;
            }
        }

        if ($nombre !== '') {
            return Producto::whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper(trim($nombre), 'UTF-8')])->first();
        }

        return null;
    }

    private function resolveProveedor(string $nombre, string $rfc): ?Proveedor
    {
        $nombre = trim($nombre);
        $rfc    = $this->normalizeRfc($rfc);

        if ($nombre === '' && $rfc === '') {
            return null;
        }

        if ($rfc !== '') {
            $prov = Proveedor::whereRaw('UPPER(TRIM(rfc)) = ?', [$rfc])->first();
            if ($prov) {
                if ($nombre !== '' && empty($prov->nombre)) {
                    $prov->nombre = $nombre;
                    $prov->save();
                }

                return $prov;
            }
        }

        if ($nombre !== '') {
            $prov = Proveedor::whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper($nombre, 'UTF-8')])->first();
            if ($prov) {
                if ($rfc !== '' && empty($prov->rfc)) {
                    $prov->rfc = $rfc;
                    $prov->save();
                }

                return $prov;
            }
        }

        return Proveedor::create([
            'nombre'    => $nombre !== '' ? $nombre : 'SIN NOMBRE',
            'rfc'       => $rfc !== '' ? $rfc : null,
            'alias'     => '',
            'direccion' => '',
            'contacto'  => '',
            'telefono'  => '',
            'correo'    => '',
        ]);
    }

    /**
     * @param array<int, string> $series
     * @return array<int, string>
     */
    private function seriesDuplicadasEnBd(array $series): array
    {
        if (empty($series)) {
            return [];
        }

        $duplicadas = Inventario::whereIn('numero_serie', $series)
            ->pluck('numero_serie')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (Schema::hasTable('numeros_serie')) {
            $extra = DB::table('numeros_serie')
                ->whereIn('numero_serie', $series)
                ->pluck('numero_serie')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $duplicadas = array_values(array_unique(array_merge($duplicadas, $extra)));
        }

        return $duplicadas;
    }

    private function recalcularStockProducto(int $codigoProducto): void
    {
        $agg = Inventario::where('codigo_producto', $codigoProducto)
            ->selectRaw('COALESCE(SUM(paquetes_restantes),0) as paquetes')
            ->selectRaw('COALESCE(SUM(piezas_sueltas),0) as piezas')
            ->selectRaw('COALESCE(SUM((paquetes_restantes * COALESCE(piezas_por_paquete,1)) + piezas_sueltas),0) as total')
            ->first();

        $producto = Producto::find($codigoProducto);
        if (!$producto) {
            return;
        }

        $producto->stock_total          = (int) ($agg->total ?? 0);
        $producto->stock_paquetes       = (int) ($agg->paquetes ?? 0);
        $producto->stock_piezas_sueltas = (int) ($agg->piezas ?? 0);
        $producto->save();
    }

    private function sanitizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeRfc(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $v = str_replace(['$', ' '], '', (string) $value);

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {
            $v = str_replace(',', '', $v);
        } elseif (preg_match('/^\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        return (float) $v;
    }

    private function parseInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }

        $n = preg_replace('/[^\d\-]/', '', (string) $value) ?? '0';

        return max(0, (int) $n);
    }

    private function normalizeTipoControl(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value), 'UTF-8');

        if (in_array($v, ['serie', 'serial', 'series'], true)) {
            return 'SERIE';
        }

        if (in_array($v, ['paquetes', 'paquete', 'caja', 'cajas'], true)) {
            return 'PAQUETES';
        }

        if (in_array($v, ['piezas', 'pieza', 'pza', 'pzas'], true)) {
            return 'PIEZAS';
        }

        return mb_strtoupper($v, 'UTF-8');
    }

    /**
     * @return array<int, string>
     */
    private function parseSeries(mixed $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;|]+/', $value) ?: [];

        return array_values(array_unique(array_filter(array_map(function ($s) {
            return mb_strtoupper(trim((string) $s), 'UTF-8');
        }, $parts))));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function parseSeriesFromPayload(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(function ($s) {
                return mb_strtoupper(trim((string) $s), 'UTF-8');
            }, $value))));
        }

        return $this->parseSeries($value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
