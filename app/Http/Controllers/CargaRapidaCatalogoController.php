<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\Importacion\ArchivoImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CargaRapidaCatalogoController extends Controller
{
    public function __construct(
        private ArchivoImportService $archivoImportService
    ) {}

    public function index()
    {
        return view('vistas-gerente.productos-gerente.carga_rapida_catalogo', [
            'preview' => false,
            'items'   => [],
            'stats'   => null,
        ]);
    }

    public function plantilla(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        // =========================
        // Hoja 1: Plantilla
        // =========================
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $headers = [
            'nombre',
            'numero_parte',
            'categoria',
            'clave_prodserv',
            'unidad',
            'stock_seguridad',
            'descripcion',
            'require_serie',
            'activo',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $sheet->freezePane('A2');
        $this->styleTemplateHeader($sheet, 'A1:I1', ['A'], ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);
        $this->autoSizeColumns($sheet, 'A', 'I');

        // =========================
        // Hoja 2: Instrucciones
        // =========================
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Instrucciones');

        $guide->setCellValue('A1', 'Plantilla de carga rápida de catálogo');
        $guide->mergeCells('A1:E1');
        $guide->setCellValue('A3', 'Campo');
        $guide->setCellValue('B3', 'Obligatorio');
        $guide->setCellValue('C3', 'Puede ir en blanco');
        $guide->setCellValue('D3', 'Descripción');
        $guide->setCellValue('E3', 'Ejemplo');

        $rows = [
            ['nombre', 'Sí', 'No', 'Nombre del producto.', 'Cable UTP Cat6 305m'],
            ['numero_parte', 'No', 'Sí', 'Si no lo capturas, el sistema puede generarlo automáticamente.', 'CBL-UTP-CAT6-305'],
            ['categoria', 'No', 'Sí', 'Categoría del producto.', 'Redes'],
            ['clave_prodserv', 'No', 'Sí', 'Clave SAT o equivalente si la manejas.', '26121609'],
            ['unidad', 'No', 'Sí', 'Unidad del producto. Si lo dejas vacío, se usa PZA.', 'PZA'],
            ['stock_seguridad', 'No', 'Sí', 'Stock mínimo recomendado.', '10'],
            ['descripcion', 'No', 'Sí', 'Descripción larga del producto.', 'Bobina de cable UTP categoría 6'],
            ['require_serie', 'No', 'Sí', '1/si/true si requiere control por serie.', '0'],
            ['activo', 'No', 'Sí', '1/si/true para activo. Si se deja vacío, se toma como activo.', '1'],
        ];

        $row = 4;
        foreach ($rows as $r) {
            $guide->fromArray($r, null, 'A' . $row);
            $row++;
        }

        $guide->setCellValue('A15', 'Nota');
        $guide->setCellValue('B15', 'La hoja "Plantilla" es la que debes llenar. Las hojas "Instrucciones" y "Ejemplos" son solo referencia.');

        $this->styleGuideSheet($guide, 'A3:E3', 'A4:E14');
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $guide->getStyle('A15:B15')->getFont()->setBold(true);
        $guide->setAutoFilter('A3:E14');
        $this->autoSizeColumns($guide, 'A', 'E');

        // =========================
        // Hoja 3: Ejemplos
        // =========================
        $examples = $spreadsheet->createSheet();
        $examples->setTitle('Ejemplos');

        foreach ($headers as $index => $header) {
            $examples->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $examples->fromArray([
            'Switch 24 puertos Gigabit',
            'SWT-24G-TP',
            'Redes',
            '43222612',
            'PZA',
            3,
            'Switch administrable de 24 puertos',
            0,
            1,
        ], null, 'A2');

        $examples->fromArray([
            'Mouse alámbrico USB',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ], null, 'A3');

        $examples->setCellValue('K1', 'Fila 2');
        $examples->setCellValue('L1', 'Ejemplo completo');
        $examples->setCellValue('K2', 'Fila 3');
        $examples->setCellValue('L2', 'Ejemplo mínimo');

        $this->styleTemplateHeader($examples, 'A1:I1', ['A'], ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);
        $examples->getStyle('K1:L2')->getFont()->setBold(true);
        $examples->getStyle('K1:L2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        $examples->getStyle('K1:L2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->autoSizeColumns($examples, 'A', 'L');

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'plantilla_carga_rapida_catalogo.xlsx', [
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

        $items = [];
        $seen  = [];

        foreach ($parsed['rows'] as $row) {
            $nombre          = $this->sanitizeText($row['nombre'] ?? $row['descripcion'] ?? '');
            $descripcion     = $this->sanitizeText($row['descripcion'] ?? $nombre);
            $numeroParteRaw  = $this->sanitizePartNumber($row['numero_parte'] ?? '');
            $categoria       = $this->sanitizeText($row['categoria'] ?? '');
            $claveProdserv   = $this->digitsOnly($row['clave_prodserv'] ?? '');
            $unidad          = $this->normalizeUnidad($row['unidad'] ?? '');
            $stockSeguridad  = $this->parseInt($row['stock_seguridad'] ?? 0);
            $requireSerie    = $this->parseBool($row['require_serie'] ?? false);
            $activo          = $this->parseBool($row['activo'] ?? true);

            $motivo = null;
            $estado = 'ACEPTAR';

            if ($nombre === '') {
                $estado = 'INVALIDO';
                $motivo = 'La fila no contiene nombre o descripción del producto.';
            }

            $existing = $this->findExistingProduct($numeroParteRaw, $nombre, $claveProdserv);

            $hash = $numeroParteRaw !== ''
                ? 'NP:' . $numeroParteRaw
                : 'NM:' . mb_strtoupper($nombre, 'UTF-8') . '|CP:' . $claveProdserv;

            if (isset($seen[$hash])) {
                $estado = 'DUPLICADO_EN_ARCHIVO';
                $motivo = 'La fila está repetida dentro del mismo archivo.';
            } else {
                $seen[$hash] = true;
            }

            $items[] = [
                '_row'            => $row['_row'] ?? null,
                'existing_id'     => $existing?->codigo_producto,
                'nombre'          => $nombre,
                'numero_parte'    => $numeroParteRaw,
                'categoria'       => $categoria,
                'clave_prodserv'  => $claveProdserv,
                'unidad'          => $unidad,
                'stock_seguridad' => $stockSeguridad,
                'descripcion'     => $descripcion,
                'require_serie'   => $requireSerie,
                'activo'          => $activo,
                'accion'          => $existing ? 'ACTUALIZAR' : 'CREAR',
                'estado'          => $estado,
                'motivo'          => $motivo,
            ];
        }

        $stats = $this->buildStats($items);

        return view('vistas-gerente.productos-gerente.carga_rapida_catalogo', [
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
                ->route('catalogo.carga_rapida.index')
                ->with('error', 'No se pudo leer el payload de confirmación.');
        }

        $ok     = 0;
        $skip   = 0;
        $errors = [];

        foreach ($items as $item) {
            $estado = (string) ($item['estado'] ?? 'INVALIDO');

            if (!in_array($estado, ['ACEPTAR'], true)) {
                $skip++;
                continue;
            }

            try {
                DB::transaction(function () use ($item) {
                    $producto = null;

                    if (!empty($item['existing_id'])) {
                        $producto = Producto::find($item['existing_id']);
                    }

                    if (!$producto) {
                        $producto = $this->findExistingProduct(
                            $item['numero_parte'] ?? '',
                            $item['nombre'] ?? '',
                            $item['clave_prodserv'] ?? ''
                        );
                    }

                    if ($producto) {
                        $producto->nombre          = $item['nombre'] ?: $producto->nombre;
                        $producto->categoria       = $item['categoria'] !== '' ? $item['categoria'] : $producto->categoria;
                        $producto->clave_prodserv  = $item['clave_prodserv'] !== '' ? $item['clave_prodserv'] : $producto->clave_prodserv;
                        $producto->unidad          = $item['unidad'] ?: ($producto->unidad ?: 'PZA');
                        $producto->stock_seguridad = isset($item['stock_seguridad']) ? (int) $item['stock_seguridad'] : ($producto->stock_seguridad ?? 0);
                        $producto->descripcion     = $item['descripcion'] !== '' ? $item['descripcion'] : $producto->descripcion;
                        $producto->require_serie   = (bool) ($item['require_serie'] ?? false);
                        $producto->activo          = (bool) ($item['activo'] ?? true);

                        if (!empty($item['numero_parte'])) {
                            $nuevoNumeroParte = $this->uniqueNumeroParteForUpdate(
                                (string) $item['numero_parte'],
                                (int) $producto->codigo_producto
                            );

                            $producto->numero_parte = $nuevoNumeroParte;
                        }

                        $producto->stock_total          = (int) ($producto->stock_total ?? 0);
                        $producto->stock_paquetes       = (int) ($producto->stock_paquetes ?? 0);
                        $producto->stock_piezas_sueltas = (int) ($producto->stock_piezas_sueltas ?? 0);

                        $producto->save();
                        return;
                    }

                    $numeroParte = $item['numero_parte'] ?: $this->generateNumeroParte(
                        (string) ($item['nombre'] ?? ''),
                        (int) ($item['_row'] ?? 0)
                    );

                    $numeroParte = $this->uniqueNumeroParte($numeroParte);

                    Producto::create([
                        'nombre'               => $item['nombre'],
                        'numero_parte'         => $numeroParte,
                        'categoria'            => $item['categoria'] ?: null,
                        'clave_prodserv'       => $item['clave_prodserv'] ?: null,
                        'unidad'               => $item['unidad'] ?: 'PZA',
                        'stock_seguridad'      => (int) ($item['stock_seguridad'] ?? 0),
                        'descripcion'          => $item['descripcion'] ?: null,
                        'activo'               => (bool) ($item['activo'] ?? true),
                        'require_serie'        => (bool) ($item['require_serie'] ?? false),
                        'stock_total'          => 0,
                        'stock_paquetes'       => 0,
                        'stock_piezas_sueltas' => 0,
                    ]);
                });

                $ok++;
            } catch (\Throwable $e) {
                $skip++;
                $errors[] = 'Fila ' . ($item['_row'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return redirect()
            ->route('catalogo.carga_rapida.index')
            ->with('success', "Carga rápida de catálogo completada. Procesados: {$ok}. Saltados: {$skip}.")
            ->with('import_errors', $errors);
    }

    private function aliases(): array
    {
        return [
            'nombre' => [
                'nombre',
                'producto',
                'descripcion',
                'descripción',
                'concepto'
            ],
            'numero_parte' => [
                'numero parte',
                'número de parte',
                'numero_parte',
                'num parte',
                'sku',
                'codigo',
                'código',
                'num identificacion',
                'numero identificacion',
                'núm identificación',
                'no parte'
            ],
            'categoria' => [
                'categoria',
                'categoría'
            ],
            'clave_prodserv' => [
                'clave prodserv',
                'clave prod/serv',
                'clave producto',
                'c_claveprodserv'
            ],
            'unidad' => [
                'unidad',
                'u',
                'unidad desc',
                'unidad descripcion',
                'unidad descripción'
            ],
            'stock_seguridad' => [
                'stock seguridad',
                'stock_seguridad',
                'stock minimo',
                'stock mínimo',
                'minimo',
                'mínimo'
            ],
            'descripcion' => [
                'descripcion larga',
                'detalle',
                'observaciones',
                'descripcion',
                'descripción'
            ],
            'require_serie' => [
                'requiere serie',
                'require serie',
                'require_serie',
                'control serie'
            ],
            'activo' => [
                'activo',
                'estado',
                'estatus'
            ],
        ];
    }

    private function buildStats(array $items): array
    {
        $stats = [
            'total'      => count($items),
            'aceptables' => 0,
            'duplicados' => 0,
            'invalidos'  => 0,
        ];

        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'ACEPTAR') {
                $stats['aceptables']++;
            } elseif (($item['estado'] ?? '') === 'DUPLICADO_EN_ARCHIVO') {
                $stats['duplicados']++;
            } else {
                $stats['invalidos']++;
            }
        }

        return $stats;
    }

    private function findExistingProduct(string $numeroParte, string $nombre, string $claveProdserv): ?Producto
    {
        if ($numeroParte !== '') {
            $p = Producto::whereRaw('UPPER(TRIM(numero_parte)) = ?', [mb_strtoupper(trim($numeroParte), 'UTF-8')])->first();
            if ($p) {
                return $p;
            }
        }

        if ($nombre !== '') {
            return Producto::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper(trim($nombre), 'UTF-8')])
                ->when($claveProdserv !== '', fn($q) => $q->where('clave_prodserv', $claveProdserv))
                ->first();
        }

        return null;
    }

    private function sanitizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeUnidad(mixed $value): string
    {
        $v = trim((string) $value);
        return $v !== '' ? mb_strtoupper($v, 'UTF-8') : 'PZA';
    }

    private function parseInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $n = preg_replace('/[^\d\-]/', '', (string) $value) ?? '0';
        return max(0, (int) $n);
    }

    private function parseBool(mixed $value): bool
    {
        $v = mb_strtolower(trim((string) $value), 'UTF-8');

        if ($v === '') {
            return false;
        }

        return in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'activo', 'activa', 'on'], true);
    }

    private function sanitizePartNumber(mixed $value): string
    {
        $v = trim((string) $value);
        $v = preg_replace('/\s+/', '', $v) ?? '';
        return mb_strtoupper($v, 'UTF-8');
    }

    private function generateNumeroParte(string $nombre, int $row): string
    {
        $slug = Str::upper(Str::slug($nombre, ''));
        $slug = $slug !== '' ? substr($slug, 0, 10) : 'SKU';
        $row  = $row > 0 ? $row : random_int(1, 9999);

        return "{$slug}-{$row}";
    }

    private function uniqueNumeroParte(string $base): string
    {
        $base = $this->sanitizePartNumber($base);
        if ($base === '') {
            $base = 'SKU-' . Str::upper(Str::random(8));
        }

        $candidate = $base;
        $i = 1;

        while (Producto::where('numero_parte', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    private function uniqueNumeroParteForUpdate(string $base, int $ignoreId): string
    {
        $base = $this->sanitizePartNumber($base);
        if ($base === '') {
            $base = 'SKU-' . Str::upper(Str::random(8));
        }

        $candidate = $base;
        $i = 1;

        while (
            Producto::where('numero_parte', $candidate)
            ->where('codigo_producto', '<>', $ignoreId)
            ->exists()
        ) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    private function styleTemplateHeader($sheet, string $range, array $requiredCols, array $optionalCols): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach ($requiredCols as $col) {
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDC2626');
        }

        foreach ($optionalCols as $col) {
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
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
    }

    private function autoSizeColumns($sheet, string $from, string $to): void
    {
        foreach (range($from, $to) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
