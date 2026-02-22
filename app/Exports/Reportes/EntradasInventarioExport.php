<?php

namespace App\Exports\Reportes;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

class EntradasInventarioExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    WithEvents,
    WithCharts,
    WithCustomStartCell,
    ShouldAutoSize
{
    use Exportable;

    /** Branding */
    protected string $brandName = 'Sistema E-Support';

    /** Título del reporte */
    protected string $titulo;

    /** Encabezados */
    protected array $cols;

    /** Filas */
    protected array $rows;

    /** Nombre hoja */
    protected string $sheetTitle = 'Entradas';

    /** Datos agregados por día para gráfica */
    protected array $dailyEntradas = [];

    /**
     * ✅ Lienzo mínimo para que la barra superior NO se corte
     * (O = índice 14, cubre sobrado la zona de la gráfica D–K)
     */
    protected int $minCanvasColIndex = 14;

    /**
     * COMPATIBLE con dos formas:
     * 1) (titulo, cols, rows)
     * 2) (cols, rows, ...extras)
     */
    public function __construct($arg1, $arg2 = null, $arg3 = null, $arg4 = null)
    {
        if (is_string($arg1) && is_array($arg2) && is_array($arg3)) {
            $this->titulo = $arg1;
            $this->cols   = $arg2;
            $this->rows   = $arg3;
        } else {
            $this->titulo = 'Entradas a Inventario';
            $this->cols   = is_array($arg1) ? $arg1 : [];
            $this->rows   = is_array($arg2) ? $arg2 : [];
        }

        // ✅ Evita el bug donde todo se recorre y empieza en B
        $this->normalizeLeadingEmptyColumn();

        // Agregado diario (para la tabla A-B y gráfica)
        $this->buildDailyEntradas();
    }

    /* =================== Datos =================== */

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->cols;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /* ======================= Estilos ======================= */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $sheet->setShowGridlines(false);

                // Canvas mínimo SIEMPRE (aunque no haya cols)
                $canvasLastColLetter = $this->indexToColumn($this->minCanvasColIndex);

                // Si no hay columnas, igual pintamos barra corporativa
                if (empty($this->cols)) {
                    $sheet->getRowDimension(1)->setRowHeight(26);
                    $sheet->getRowDimension(2)->setRowHeight(18);

                    $sheet->mergeCells("A1:{$canvasLastColLetter}1");
                    $sheet->setCellValue('A1', $this->titulo);

                    $sheet->getStyle("A1:{$canvasLastColLetter}1")->getFont()
                        ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');

                    $sheet->getStyle("A1:{$canvasLastColLetter}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');

                    $sheet->getStyle("A1:{$canvasLastColLetter}1")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $sheet->mergeCells("A2:{$canvasLastColLetter}2");
                    $sheet->setCellValue('A2', $this->brandName . ' — Exportado el ' . Carbon::now()->format('d/m/Y H:i'));

                    $sheet->getStyle("A2:{$canvasLastColLetter}2")->getFont()
                        ->setSize(10)->getColor()->setARGB('FF334155');

                    $sheet->getStyle("A2:{$canvasLastColLetter}2")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

                    $sheet->getStyle("A2:{$canvasLastColLetter}2")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    return;
                }

                $headerRow     = 5;
                $lastColIndex  = count($this->cols) - 1;
                $lastColLetter = $this->indexToColumn($lastColIndex);
                $lastDataRow   = $headerRow + count($this->rows);

                // ✅ Canvas = max(última col real, O)
                $canvasLastColIndex  = max($lastColIndex, $this->minCanvasColIndex);
                $canvasLastColLetter = $this->indexToColumn($canvasLastColIndex);

                // Alturas corporativas
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Freeze igual que master
                $sheet->freezePane('A6');

                /* ---------- Título (MISMO MASTER) ---------- */
                $sheet->mergeCells("A1:{$canvasLastColLetter}1");
                $sheet->setCellValue('A1', $this->titulo);

                $sheet->getStyle("A1:{$canvasLastColLetter}1")->getFont()
                    ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle("A1:{$canvasLastColLetter}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A'); // slate-900

                $sheet->getStyle("A1:{$canvasLastColLetter}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                /* ---------- Subtítulo / metadata (MISMO MASTER) ---------- */
                $sheet->mergeCells("A2:{$canvasLastColLetter}2");
                $sheet->setCellValue('A2', $this->brandName . ' — Exportado el ' . Carbon::now()->format('d/m/Y H:i'));

                $sheet->getStyle("A2:{$canvasLastColLetter}2")->getFont()
                    ->setSize(10)->getColor()->setARGB('FF334155'); // slate-700

                $sheet->getStyle("A2:{$canvasLastColLetter}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9'); // slate-50

                $sheet->getStyle("A2:{$canvasLastColLetter}2")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                /* ---------- Encabezados tabla (MISMO MASTER) ---------- */
                $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";

                $sheet->getStyle($headerRange)->getFont()
                    ->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8'); // blue-700

                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->setAutoFilter($headerRange);

                /* ---------- Bordes + zebra (MISMO MASTER) ---------- */
                if ($lastDataRow >= $headerRow) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";

                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0'); // slate-200

                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);

                        if (($r % 2) === 0) {
                            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC'); // slate-50
                        }
                    }

                    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()->setWrapText(false);
                }

                /* ---------- Formatos por tipo (solo visual, master) ---------- */
                $colMeta = $this->detectColumnTypes($this->cols);

                foreach ($colMeta as $colIndex => $type) {
                    if ($lastDataRow < ($headerRow + 1)) continue;

                    $letter       = $this->indexToColumn($colIndex);
                    $dataColRange = "{$letter}" . ($headerRow + 1) . ":{$letter}{$lastDataRow}";

                    if ($type === 'currency') {
                        $sheet->getStyle($dataColRange)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } elseif ($type === 'int') {
                        $sheet->getStyle($dataColRange)->getNumberFormat()->setFormatCode('0');
                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    } elseif ($type === 'center') {
                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    } else {
                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }

                /* ---------- Tabla auxiliar A-B (MISMO LOOK master) ---------- */
                if (!empty($this->dailyEntradas)) {
                    $metricsStartRow = $lastDataRow + 2;

                    // ✅ Tabla limpia: header + filas (ideal para graficar)
                    $metrics = [
                        ['Fecha (agrupada)', 'Cantidad total por día'],
                    ];

                    foreach ($this->dailyEntradas as $day) {
                        $metrics[] = [$day['label'], (int) $day['total']];
                    }

                    $sheet->fromArray($metrics, null, 'A' . $metricsStartRow);

                    // Header gris
                    $metricsHeaderRange = "A{$metricsStartRow}:B{$metricsStartRow}";
                    $sheet->getStyle($metricsHeaderRange)->getFont()->setBold(true)->setSize(10);
                    $sheet->getStyle($metricsHeaderRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
                    $sheet->getStyle($metricsHeaderRange)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    $metricsEndRow = $metricsStartRow + count($metrics) - 1;
                    $metricsRange  = "A{$metricsStartRow}:B{$metricsEndRow}";
                    $sheet->getStyle($metricsRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

                    // Alineación + formato entero
                    $sheet->getStyle("A" . ($metricsStartRow + 1) . ":A{$metricsEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("B" . ($metricsStartRow + 1) . ":B{$metricsEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("B" . ($metricsStartRow + 1) . ":B{$metricsEndRow}")
                        ->getNumberFormat()->setFormatCode('0');

                    $sheet->getRowDimension($metricsStartRow)->setRowHeight(18);
                }
            },
        ];
    }

    /* ============================ Gráfica ============================= */

    public function charts()
    {
        if (empty($this->cols) || empty($this->dailyEntradas)) {
            return [];
        }

        $sheetTitle  = $this->sheetTitle;
        $headerRow   = 5;
        $lastDataRow = $headerRow + count($this->rows);

        // Debe coincidir con AfterSheet()
        $metricsStartRow = $lastDataRow + 2;

        // Header en metricsStartRow, data inicia metricsStartRow+1
        $headerMetrics  = $metricsStartRow;
        $firstDataRow   = $metricsStartRow + 1;
        $lastMetricsRow = $firstDataRow + count($this->dailyEntradas) - 1;

        $labelCell = "'{$sheetTitle}'!\$B\${$headerMetrics}";
        $catRange  = "'{$sheetTitle}'!\$A\${$firstDataRow}:\$A\${$lastMetricsRow}";
        $valRange  = "'{$sheetTitle}'!\$B\${$firstDataRow}:\$B\${$lastMetricsRow}";

        $dataSeriesLabels = [
            new DataSeriesValues('String', $labelCell, null, 1),
        ];

        $xAxisTickValues = [
            new DataSeriesValues('String', $catRange, null, count($this->dailyEntradas)),
        ];

        $dataSeriesValues = [
            new DataSeriesValues('Number', $valRange, null, count($this->dailyEntradas)),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend   = new Legend(Legend::POSITION_RIGHT, null, false);
        $title    = new ChartTitle('Entradas por día');

        $chart = new Chart(
            'entradas_por_dia',
            $title,
            $legend,
            $plotArea
        );

        // ✅ MISMA ubicación que master: D-K
        $topRow    = $metricsStartRow;
        $bottomRow = $topRow + 18;

        $chart->setTopLeftPosition('D' . $topRow);
        $chart->setBottomRightPosition('K' . $bottomRow);

        return [$chart];
    }

    /* ======================= Helpers ========================== */

    /**
     * ✅ Si llega una primera columna vacía, elimina esa columna en cols y rows
     * (evita que TODO se recorra y empiece en B).
     */
    protected function normalizeLeadingEmptyColumn(): void
    {
        if (empty($this->cols)) return;

        $first = trim((string)($this->cols[0] ?? ''));
        if ($first !== '') return;

        $allEmpty = true;
        foreach ($this->rows as $r) {
            if (!is_array($r)) continue;
            if (!array_key_exists(0, $r)) continue;

            $v = trim((string)($r[0] ?? ''));
            if ($v !== '') { $allEmpty = false; break; }
        }

        if (!$allEmpty) return;

        array_shift($this->cols);
        foreach ($this->rows as $i => $r) {
            if (!is_array($r) || !array_key_exists(0, $r)) continue;
            $tmp = $r;
            array_shift($tmp);
            $this->rows[$i] = $tmp;
        }
    }

    /**
     * Agrupa "Cantidad" por "Fecha de entrada" (para tabla A-B + gráfica).
     */
    protected function buildDailyEntradas(): void
    {
        if (empty($this->cols) || empty($this->rows)) {
            $this->dailyEntradas = [];
            return;
        }

        $idxFecha = null;
        $idxCant  = null;

        foreach ($this->cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            // Acepta "Fecha de entrada" o similar
            if ($idxFecha === null && str_contains($lc, 'fecha') && str_contains($lc, 'entrada')) {
                $idxFecha = $i;
            }
            if ($idxCant === null && (str_contains($lc, 'cantidad') || str_contains($lc, 'ingresad'))) {
                $idxCant = $i;
            }
        }

        if ($idxFecha === null || $idxCant === null) {
            $this->dailyEntradas = [];
            return;
        }

        $daily = [];

        foreach ($this->rows as $row) {
            if (!is_array($row)) continue;

            // Soporta arrays numéricos y asociativos
            $fechaStr = $row[$idxFecha] ?? ($row['Fecha de entrada'] ?? $row['Fecha'] ?? null);
            $cantStr  = $row[$idxCant]  ?? ($row['Cantidad'] ?? null);

            if ($fechaStr === null || $fechaStr === '' || $cantStr === null) continue;

            $c = null;
            try {
                $c = Carbon::createFromFormat('d/m/Y', (string)$fechaStr);
            } catch (\Throwable $e) {
                try { $c = Carbon::parse((string)$fechaStr); }
                catch (\Throwable $e2) { $c = null; }
            }

            $key   = $c ? $c->format('Y-m-d') : (string)$fechaStr;
            $label = $c ? $c->format('d/m/Y') : (string)$fechaStr;

            $cantidad = (float) str_replace(['$', ',', ' '], '', (string)$cantStr);

            if (!isset($daily[$key])) {
                $daily[$key] = ['label' => $label, 'total' => 0.0];
            }
            $daily[$key]['total'] += $cantidad;
        }

        ksort($daily);
        $this->dailyEntradas = array_values($daily);
    }

    /**
     * Detecta tipos por encabezado (solo para formato visual).
     */
    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            if (str_contains($lc, 'total') || str_contains($lc, 'importe') || str_contains($lc, 'monto') || str_contains($lc, 'precio') || str_contains($lc, 'costo')) {
                $out[$i] = 'currency';
                continue;
            }

            if (str_contains($lc, 'cantidad') || str_contains($lc, 'piez') || str_contains($lc, 'paquet') || str_contains($lc, 'stock') || str_contains($lc, 'num') || str_contains($lc, 'serie') || str_contains($lc, 'id')) {
                $out[$i] = 'int';
                continue;
            }

            if (str_contains($lc, 'folio') || str_contains($lc, 'fecha') || str_contains($lc, 'hora') || str_contains($lc, 'vigenc') || str_contains($lc, 'moneda') || str_contains($lc, 'estado') || str_contains($lc, 'tipo')) {
                $out[$i] = 'center';
                continue;
            }

            $out[$i] = 'text';
        }

        return $out;
    }

    /**
     * Convierte índice 0-based a letra Excel (0=>A, 1=>B, 26=>AA, ...).
     */
    protected function indexToColumn(int $index): string
    {
        $index += 1;
        $column = '';

        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index  = (int) floor(($index - 1) / 26);
        }

        return $column;
    }
}
