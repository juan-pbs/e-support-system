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

class CotizacionesEstadoExport implements
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

    /** Título del reporte (fila 1). */
    protected string $titulo;

    /** Encabezados de la tabla principal. */
    protected array $cols;

    /** Filas de la tabla principal. */
    protected array $rows;

    /** Nombre de la hoja. */
    protected string $sheetTitle = 'Cotizaciones';

    /** Totales para la tabla de métricas. */
    protected int $totalProcessed = 0;
    protected int $totalEdited = 0;

    /**
     * COMPATIBLE con dos formas:
     *
     * 1) (cols, rows, moneda, tipoCambio)  -> NO rompe tu controlador actual
     * 2) (cols, rows, totalProcessed, totalEdited) -> modo nuevo (ints)
     */
    public function __construct(array $cols, array $rows, $arg3 = null, $arg4 = null)
    {
        $this->titulo = 'Cotizaciones por Estado';
        $this->cols   = $cols;
        $this->rows   = $rows;

        // ✅ Si llegan números, se usan directo (modo nuevo)
        if (is_numeric($arg3) && is_numeric($arg4)) {
            $this->totalProcessed = (int) $arg3;
            $this->totalEdited    = (int) $arg4;
            return;
        }

        // ✅ Si llegan (moneda, tipoCambio) u otra cosa, calculamos totales desde rows
        [$tp, $te] = $this->computeTotalsFromRows($cols, $rows);
        $this->totalProcessed = $tp;
        $this->totalEdited    = $te;
    }

    /* =================== Datos que se escriben en la hoja =================== */

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
        // Dejamos espacio para título/subtítulo/metadata
        return 'A5';
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /* ======================= Estilos / diseño de hoja ======================= */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                if (empty($this->cols)) {
                    // Aun sin cols, dejar título bonito
                    $sheet->mergeCells("A1:D1");
                    $sheet->setCellValue('A1', $this->titulo);
                    return;
                }

                $headerRow     = 5;
                $lastColIndex  = count($this->cols) - 1;
                $lastColLetter = $this->indexToColumn($lastColIndex);
                $lastDataRow   = $headerRow + count($this->rows);

                // ===== Ajustes generales =====
                $sheet->setShowGridlines(false);

                // Tamaños de filas (más “corporativo” y compacto)
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Congelar encabezado (fila 5) -> empieza data en fila 6
                $sheet->freezePane('A6');

                /* ---------- Título ---------- */
                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', $this->titulo);

                $sheet->getStyle("A1:{$lastColLetter}1")->getFont()
                    ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A'); // slate-900

                $sheet->getStyle("A1:{$lastColLetter}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                /* ---------- Subtítulo / metadata ---------- */
                $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->setCellValue(
                    'A2',
                    $this->brandName . ' — Exportado el ' . Carbon::now()->format('d/m/Y H:i')
                );

                $sheet->getStyle("A2:{$lastColLetter}2")->getFont()
                    ->setSize(10)->getColor()->setARGB('FF334155'); // slate-700

                $sheet->getStyle("A2:{$lastColLetter}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9'); // slate-50

                $sheet->getStyle("A2:{$lastColLetter}2")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                /* ---------- Encabezados tabla ---------- */
                $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";

                $sheet->getStyle($headerRange)->getFont()
                    ->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8'); // blue-700

                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // AutoFiltro en encabezados
                $sheet->setAutoFilter($headerRange);

                /* ---------- Bordes + zebra + compactación de tabla ---------- */
                if ($lastDataRow >= $headerRow) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";

                    // Bordes suaves
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0'); // slate-200

                    // Alineación vertical en toda la tabla
                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Zebra (solo data, no header)
                    for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);

                        if (($r % 2) === 0) {
                            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC'); // slate-50
                        }
                    }

                    // Padding visual (simulado con alineación y wrap off para compactar)
                    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()->setWrapText(false);
                }

                /* ---------- Formatos por tipo de columna (sin tocar datos) ---------- */
                $colMeta = $this->detectColumnTypes($this->cols);

                foreach ($colMeta as $colIndex => $type) {
                    $letter = $this->indexToColumn($colIndex);

                    // No aplicar al header, solo a data
                    if ($lastDataRow < ($headerRow + 1)) {
                        continue;
                    }

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
                        // text/default
                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }

                /* ---------- Tabla de métricas (para la gráfica) ---------- */
                $metricsStartRow = $lastDataRow + 2;

                $metrics = [
                    ['Métrica', 'Cantidad'],
                    ['Cotizaciones procesadas', $this->totalProcessed],
                    ['Cotizaciones editadas',  $this->totalEdited],
                ];

                $sheet->fromArray($metrics, null, 'A' . $metricsStartRow);

                $metricsHeaderRange = "A{$metricsStartRow}:B{$metricsStartRow}";
                $sheet->getStyle($metricsHeaderRange)->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle($metricsHeaderRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0'); // slate-200
                $sheet->getStyle($metricsHeaderRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $metricsEndRow = $metricsStartRow + count($metrics) - 1;
                $metricsRange  = "A{$metricsStartRow}:B{$metricsEndRow}";
                $sheet->getStyle($metricsRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

                $sheet->getStyle("B" . ($metricsStartRow + 1) . ":B{$metricsEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Un poco de aire antes de la gráfica
                $sheet->getRowDimension($metricsStartRow)->setRowHeight(18);
            },
        ];
    }

    /* ============================ Gráfica ============================= */

    public function charts()
    {
        if (empty($this->cols)) {
            return [];
        }

        $sheetTitle  = $this->sheetTitle;
        $headerRow   = 5;
        $lastDataRow = $headerRow + count($this->rows);

        // Debe coincidir con AfterSheet
        $metricsStartRow = $lastDataRow + 2;
        $headerMetrics   = $metricsStartRow;       // "Métrica / Cantidad"
        $firstDataRow    = $metricsStartRow + 1;   // Procesadas
        $lastMetricsRow  = $metricsStartRow + 2;   // Editadas

        $labelCell = "'{$sheetTitle}'!\$B\${$headerMetrics}";
        $catRange  = "'{$sheetTitle}'!\$A\${$firstDataRow}:\$A\${$lastMetricsRow}";
        $valRange  = "'{$sheetTitle}'!\$B\${$firstDataRow}:\$B\${$lastMetricsRow}";

        $dataSeriesLabels = [
            new DataSeriesValues('String', $labelCell, null, 1),
        ];

        $xAxisTickValues = [
            new DataSeriesValues('String', $catRange, null, 2),
        ];

        $dataSeriesValues = [
            new DataSeriesValues('Number', $valRange, null, 2),
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
        $title    = new ChartTitle('Procesadas vs Editadas');

        $chart = new Chart(
            'cotizaciones_estado_chart',
            $title,
            $legend,
            $plotArea
        );

        // Ubicar gráfica a la derecha de métricas
        $topRow    = $metricsStartRow;
        $bottomRow = $topRow + 18;

        $chart->setTopLeftPosition('D' . $topRow);
        $chart->setBottomRightPosition('K' . $bottomRow);

        return [$chart];
    }

    /* ======================= Helpers internos ========================== */

    protected function computeTotalsFromRows(array $cols, array $rows): array
    {
        $idxProces = null;
        $idxEdit   = null;

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            if ($idxProces === null && (
                str_contains($lc, 'proces') ||
                str_contains($lc, 'pdf') ||
                str_contains($lc, 'env')
            )) {
                $idxProces = $i;
            }

            if ($idxEdit === null && (
                str_contains($lc, 'edit') ||
                str_contains($lc, 'edicion')
            )) {
                $idxEdit = $i;
            }
        }

        $totalProcessed = 0;
        $totalEdited    = 0;

        foreach ($rows as $r) {
            if (!is_array($r)) continue;

            // filas numéricas
            if ($idxProces !== null) $totalProcessed += (int) ($r[$idxProces] ?? 0);
            else $totalProcessed += (int) ($r['Procesos'] ?? $r['process_count'] ?? 0);

            if ($idxEdit !== null) $totalEdited += (int) ($r[$idxEdit] ?? 0);
            else $totalEdited += (int) ($r['Ediciones'] ?? $r['edit_count'] ?? 0);
        }

        return [$totalProcessed, $totalEdited];
    }

    /**
     * Detecta tipos por encabezado (solo para formato visual).
     */
    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            // moneda/importe/total -> número con 2 decimales
            if (str_contains($lc, 'total') || str_contains($lc, 'importe') || str_contains($lc, 'monto') || str_contains($lc, 'precio')) {
                $out[$i] = 'currency';
                continue;
            }

            // contadores
            if (str_contains($lc, 'proces') || str_contains($lc, 'edit') || str_contains($lc, 'cantidad')) {
                $out[$i] = 'int';
                continue;
            }

            // columnas centradas típicas
            if (str_contains($lc, 'folio') || str_contains($lc, 'fecha') || str_contains($lc, 'vigenc') || str_contains($lc, 'moneda') || str_contains($lc, 'estado')) {
                $out[$i] = 'center';
                continue;
            }

            $out[$i] = 'text';
        }

        return $out;
    }

    /**
     * Convierte índice 0-based a letra de columna Excel
     * (0 => A, 1 => B, ..., 26 => AA, ...).
     */
    protected function indexToColumn(int $index): string
    {
        $index += 1; // 1-based
        $column = '';

        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index  = (int) floor(($index - 1) / 26);
        }

        return $column;
    }
}
