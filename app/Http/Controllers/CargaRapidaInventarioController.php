<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Ordenes\OrdenServicioService;
use App\Services\Importacion\ArchivoImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CargaRapidaInventarioController extends Controller
{
    public function __construct(
        private ArchivoImportService $archivoImportService,
        private OrdenServicioService $ordenService
    ) {}

    public function index()
    {
        return view('vistas-gerente.inventario-gerente.carga_rapida_inventario', [
            'preview' => false,
            'items'   => [],
            'stats'   => null,
        ]);
    }

    public function plantilla(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $this->applyWorkbookMeta(
            $spreadsheet,
            'Plantilla de carga rapida de inventario',
            'Plantilla para importar entradas de inventario en Sistema E-Support.'
        );

        // =========================
        // Hoja 1: Plantilla
        // =========================
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $headers = [
            'numero_parte',
            'codigo_producto',
            'proveedor',
            'rfc',
            'costo',
            'precio',
            'tipo_control',
            'cantidad',
            'piezas_por_paquete',
            'numeros_serie',
            'fecha_entrada',
            'fecha_caducidad',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $sheet->fromArray(['__EJEMPLO__ SWT-24G-TP (NO_IMPORTAR)','', 'Cisco','XAXX010101000',1250.50,1699.00,'PAQUETES',5,20,'','2026-03-08',''], null, 'A2');
        $sheet->fromArray(['__EJEMPLO__ MOUSE-USB-BASICO (NO_IMPORTAR)','', '', '',120.00,179.00,'PIEZAS',12,'','','2026-03-08',''], null, 'A3');

        $sheet->fromArray(['__EJEMPLO__ LAPTOP-I5-14 (NO_IMPORTAR)','', 'Dell','XAXX010101000',9800.00,11500.00,'SERIE','','','SN-LAP-0001,SN-LAP-0002','2026-03-08',''], null, 'A4');
        $sheet->freezePane('A2');
        $this->styleTemplateHeader(
            $sheet,
            'A1:L1',
            ['A', 'G'],
            ['B', 'C', 'D', 'E', 'F', 'H', 'I', 'J', 'K', 'L']
        );
        $this->styleTemplateBody($sheet, 'A2:L4');
        $sheet->setAutoFilter('A1:L1');
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getStyle('A2:J4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('K2:L4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getTabColor()->setRGB('1D4ED8');
        $this->autoSizeColumns($sheet, 'A', 'L');

        // =========================
        // Hoja 2: Instrucciones
        // =========================
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Instrucciones');

        $guide->setCellValue('A1', 'Plantilla de carga rápida de inventario');
        $guide->mergeCells('A1:F1');
        $guide->setCellValue('A2', 'Llena solo la hoja "Plantilla". Esta guia y la hoja "Ejemplos" son de referencia.');
        $guide->mergeCells('A2:F2');
        $guide->setCellValue('A3', 'Campo');
        $guide->setCellValue('B3', 'Obligatorio');
        $guide->setCellValue('C3', 'Puede ir en blanco');
        $guide->setCellValue('D3', 'Descripción');
        $guide->setCellValue('E3', 'Aplica cuando');
        $guide->setCellValue('F3', 'Ejemplo');

        $rows = [
            ['numero_parte', 'Sí*', 'No', 'Número de parte del producto existente.', 'Usa este campo o codigo_producto', 'SWT-24G-TP'],
            ['codigo_producto', 'Sí*', 'Sí', 'ID del producto existente.', 'Usa este campo o numero_parte', '15'],
            ['proveedor', 'No', 'Sí', 'Nombre del proveedor.', 'Siempre', 'Cisco'],
            ['rfc', 'No', 'Sí', 'RFC del proveedor.', 'Siempre', 'XAXX010101000'],
            ['costo', 'No', 'Sí', 'Costo unitario.', 'Siempre', '1250.50'],
            ['precio', 'No', 'Sí', 'Precio de venta unitario.', 'Siempre', '1699.00'],
            ['tipo_control', 'Sí', 'No', 'PIEZAS, PAQUETES o SERIE.', 'Siempre', 'PIEZAS'],
            ['cantidad', 'Sí**', 'Depende', 'Cantidad de entrada.', 'PIEZAS o PAQUETES', '12'],
            ['piezas_por_paquete', 'Sí***', 'Depende', 'Piezas que contiene cada paquete.', 'PAQUETES', '20'],
            ['numeros_serie', 'Sí****', 'Depende', 'Series separadas por coma o salto de línea.', 'SERIE', 'SN001,SN002,SN003'],
            ['fecha_entrada', 'No', 'Sí', 'Fecha de entrada.', 'Siempre', '2026-03-08'],
            ['fecha_caducidad', 'No', 'Sí', 'Fecha de caducidad si aplica.', 'Opcional', '2027-03-08'],
        ];

        $row = 4;
        foreach ($rows as $r) {
            $guide->fromArray($r, null, 'A' . $row);
            $row++;
        }

        $guide->setCellValue('A18', '*');
        $guide->setCellValue('B18', 'Debes capturar numero_parte o codigo_producto.');
        $guide->setCellValue('A19', '**');
        $guide->setCellValue('B19', 'En SERIE, la cantidad se toma del número de series.');
        $guide->setCellValue('A20', '***');
        $guide->setCellValue('B20', 'Solo aplica cuando tipo_control = PAQUETES.');
        $guide->setCellValue('A21', '****');
        $guide->setCellValue('B21', 'Solo aplica cuando tipo_control = SERIE.');

        $this->styleGuideSheet($guide, 'A3:F3', 'A4:F15');
        $guide->getStyle('A1:F1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
        $guide->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
        $guide->getStyle('A2:F2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $guide->getStyle('A1:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $guide->getStyle('D4:D15')->getAlignment()->setWrapText(true);
        $guide->getStyle('E4:E15')->getAlignment()->setWrapText(true);
        $guide->getStyle('F4:F15')->getAlignment()->setWrapText(true);
        $guide->getRowDimension(1)->setRowHeight(24);
        $guide->getRowDimension(2)->setRowHeight(22);
        $guide->getStyle('A18:B21')->getFont()->setBold(true);
        $guide->getStyle('A18:B21')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        $guide->setAutoFilter('A3:F15');
        $guide->getTabColor()->setRGB('0F172A');
        $this->autoSizeColumns($guide, 'A', 'F');

        // =========================
        // Hoja 3: Ejemplos
        // =========================
        $examples = $spreadsheet->createSheet();
        $examples->setTitle('Ejemplos');

        foreach ($headers as $index => $header) {
            $examples->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Ejemplo PAQUETES
        $examples->fromArray([
            'SWT-24G-TP',
            '',
            'Cisco',
            'XAXX010101000',
            1250.50,
            1699.00,
            'PAQUETES',
            5,
            20,
            '',
            '2026-03-08',
            '',
        ], null, 'A2');

        // Ejemplo PIEZAS
        $examples->fromArray([
            'MOUSE-USB-BASICO',
            '',
            '',
            '',
            '',
            '',
            'PIEZAS',
            10,
            '',
            '',
            '',
            '',
        ], null, 'A3');

        // Ejemplo SERIE
        $examples->fromArray([
            'LAPTOP-I5-14',
            '',
            'Dell',
            'XAXX010101000',
            9800.00,
            11500.00,
            'SERIE',
            '',
            '',
            'SN-LAP-0001,SN-LAP-0002',
            '2026-03-08',
            '',
        ], null, 'A4');

        $examples->setCellValue('N1', 'Fila 2');
        $examples->setCellValue('O1', 'Ejemplo PAQUETES');
        $examples->setCellValue('N2', 'Fila 3');
        $examples->setCellValue('O2', 'Ejemplo PIEZAS');
        $examples->setCellValue('N3', 'Fila 4');
        $examples->setCellValue('O3', 'Ejemplo SERIE');

        $this->styleTemplateHeader(
            $examples,
            'A1:L1',
            ['A', 'G'],
            ['B', 'C', 'D', 'E', 'F', 'H', 'I', 'J', 'K', 'L']
        );
        $this->styleTemplateBody($examples, 'A2:L4');
        $examples->setAutoFilter('A1:L4');
        $examples->freezePane('A2');
        $examples->getStyle('A2:J4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $examples->getStyle('K2:L4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->styleLegendBlock($examples, 'N1:O3');
        $examples->getRowDimension(1)->setRowHeight(22);
        $examples->getRowDimension(2)->setRowHeight(20);
        $examples->getRowDimension(3)->setRowHeight(20);
        $examples->getRowDimension(4)->setRowHeight(20);
        $examples->getTabColor()->setRGB('334155');
        $this->autoSizeColumns($examples, 'A', 'O');

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'plantilla_carga_rapida_inventario.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

        $items      = [];
        $seenSeries = [];

        foreach ($parsed['rows'] as $row) {
            if ($this->isTemplateExampleRow($row)) {
                continue;
            }
            $codigoProducto  = $this->parseInt($row['codigo_producto'] ?? null);
            $numeroParte     = $this->sanitizeText($row['numero_parte'] ?? '');
            $nombreProducto  = $this->sanitizeText($row['producto'] ?? '');
            $producto        = $this->findProduct($codigoProducto, $numeroParte, $nombreProducto);

            $proveedorNombre = $this->sanitizeText($row['proveedor'] ?? '');
            $proveedorRfc    = $this->normalizeRfc($row['rfc'] ?? '');
            $costo           = $this->parseMoney($row['costo'] ?? 0);
            $precio          = $this->parseMoney($row['precio'] ?? $costo);
            $tipoControl     = $this->normalizeTipoControl($row['tipo_control'] ?? '');
            $cantidad        = $this->parseInt($row['cantidad'] ?? 0);
            $piezasPaquete   = $this->parseInt($row['piezas_por_paquete'] ?? 0);
            $fechaEntrada    = $this->normalizeDate($row['fecha_entrada'] ?? '') ?: now()->toDateString();
            $fechaCaducidad  = $this->normalizeDate($row['fecha_caducidad'] ?? '');
            $series          = $this->parseSeries($row['numeros_serie'] ?? '');

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
                    $producto  = Producto::findOrFail($item['producto_id']);
                    $proveedor = $this->resolveProveedor(
                        (string) ($item['proveedor'] ?? ''),
                        (string) ($item['rfc'] ?? '')
                    );

                    $base = $this->filterInventarioColumns([
                        'codigo_producto' => $producto->codigo_producto,
                        'clave_proveedor' => $proveedor?->clave_proveedor,
                        'costo'           => (float) ($item['costo'] ?? 0),
                        'precio'          => (float) ($item['precio'] ?? 0),
                        'tipo_control'    => $item['tipo_control'],
                        'fecha_entrada'   => $item['fecha_entrada'] ?: now()->toDateString(),
                        'hora_entrada'    => now()->format('H:i:s'),
                        'fecha_caducidad' => $item['fecha_caducidad'] ?: null,
                    ]);

                    if ($item['tipo_control'] === 'SERIE') {
                        $series = $this->parseSeriesFromPayload($item['numeros_serie'] ?? []);
                        $duplicadas = $this->seriesDuplicadasEnBd($series);

                        if (!empty($duplicadas)) {
                            throw new \RuntimeException(
                                'Estas series ya existen: ' . implode(', ', array_slice($duplicadas, 0, 20))
                            );
                        }

                        foreach ($series as $serie) {
                            $payload = $this->filterInventarioColumns(array_merge($base, [
                                'cantidad_ingresada' => 1,
                                'piezas_por_paquete' => null,
                                'paquetes_restantes' => 0,
                                'piezas_sueltas'     => 1,
                                'numero_serie'       => $serie,
                            ]));

                            Inventario::create($payload);
                        }
                    } elseif ($item['tipo_control'] === 'PAQUETES') {
                        $payload = $this->filterInventarioColumns(array_merge($base, [
                            'cantidad_ingresada' => (int) $item['cantidad'],
                            'piezas_por_paquete' => (int) $item['piezas_por_paquete'],
                            'paquetes_restantes' => (int) $item['cantidad'],
                            'piezas_sueltas'     => 0,
                            'numero_serie'       => null,
                        ]));

                        Inventario::create($payload);
                    } else {
                        $payload = $this->filterInventarioColumns(array_merge($base, [
                            'cantidad_ingresada' => (int) $item['cantidad'],
                            'piezas_por_paquete' => null,
                            'paquetes_restantes' => 0,
                            'piezas_sueltas'     => (int) $item['cantidad'],
                            'numero_serie'       => null,
                        ]));

                        Inventario::create($payload);
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
        $this->ordenService->refreshProductStockTotals($codigoProducto);
    }

    private function filterInventarioColumns(array $payload): array
    {
        $filtered = [];
        foreach ($payload as $column => $value) {
            if (Schema::hasColumn('inventario', $column)) {
                $filtered[$column] = $value;
            }
        }
        return $filtered;
    }

    private function isTemplateExampleRow(array $row): bool
    {
        $numeroParte = mb_strtoupper(trim((string) ($row['numero_parte'] ?? '')), 'UTF-8');

        return str_contains($numeroParte, '__EJEMPLO__')
            || str_contains($numeroParte, 'NO_IMPORTAR');
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

    private function styleTemplateHeader($sheet, string $range, array $requiredCols, array $optionalCols): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

        foreach ($requiredCols as $col) {
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDC2626');
        }

        foreach ($optionalCols as $col) {
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8');
        }
    }

    private function styleGuideSheet($sheet, string $headerRange, string $bodyRange): void
    {
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle($bodyRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($bodyRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        [$start, $end] = explode(':', $bodyRange);
        $startRow = (int) preg_replace('/\\D+/', '', $start);
        $endCol = preg_replace('/\\d+/', '', $end);
        $endRow = (int) preg_replace('/\\D+/', '', $end);
        for ($r = $startRow; $r <= $endRow; $r++) {
            if (($r % 2) === 0) {
                $sheet->getStyle("A{$r}:{$endCol}{$r}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
            }
        }
    }

    private function styleTemplateBody($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
    }

    private function styleLegendBlock($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FF0F172A');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function applyWorkbookMeta(Spreadsheet $spreadsheet, string $title, string $description): void
    {
        $spreadsheet->getProperties()
            ->setCreator('Sistema E-Support')
            ->setLastModifiedBy('Sistema E-Support')
            ->setTitle($title)
            ->setSubject('Carga rapida')
            ->setDescription($description)
            ->setCategory('Plantillas');
    }

    private function autoSizeColumns($sheet, string $from, string $to): void
    {
        foreach (range($from, $to) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
