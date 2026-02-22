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

class VentasExport implements
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

    protected string $titulo;
    protected array $cols;
    protected array $rows;

    protected string $sheetTitle = 'Ventas';

    /**
     * Datos agregados por día para la gráfica (en MXN estimado)
     * [
     *   ['label' => '01/12/2025', 'total' => 1234.56],
     *   ...
     * ]
     */
    protected array $dailyVentas = [];

    /**
     * Meta de totales por moneda (viene de VentasReport)
     */
    protected array $meta = [];

    /** Tipo de cambio MXN por 1 USD. */
    protected float $tipoCambio = 1.0;

    public function __construct(string $titulo, array $cols, array $rows, array $meta = [], float $tipoCambio = 1.0)
    {
        $this->titulo     = $titulo;
        $this->cols       = $cols;
        $this->rows       = $rows;
        $this->meta       = $meta;
        $this->tipoCambio = $tipoCambio;

        $this->buildDailyVentas();
    }

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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                $sheet->setShowGridlines(false);

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(5)->setRowHeight(20);

                $sheet->freezePane('A6');

                if (empty($this->cols)) {
                    $sheet->mergeCells("A1:D1");
                    $sheet->setCellValue('A1', $this->titulo);

                    $sheet->getStyle("A1:D1")->getFont()
                        ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                    $sheet->getStyle("A1:D1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
                    $sheet->getStyle("A1:D1")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    return;
                }

                $headerRow     = 5;
                $lastColIndex  = count($this->cols) - 1;
                $lastColLetter = $this->indexToColumn($lastColIndex);
                $lastDataRow   = $headerRow + count($this->rows);

                /* ---------- Título (barra oscura) ---------- */
                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', $this->titulo);

                $sheet->getStyle("A1:{$lastColLetter}1")->getFont()
                    ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');

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
                    ->setSize(10)->getColor()->setARGB('FF334155');

                $sheet->getStyle("A2:{$lastColLetter}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

                $sheet->getStyle("A2:{$lastColLetter}2")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                /* ---------- Encabezados tabla ---------- */
                $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";

                $sheet->getStyle($headerRange)->getFont()
                    ->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8');

                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->setAutoFilter($headerRange);

                /* ---------- Bordes + zebra ---------- */
                if ($lastDataRow >= $headerRow) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";

                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0');

                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);

                        if (($r % 2) === 0) {
                            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }

                    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()->setWrapText(false);
                }

                /* ---------- Formatos por tipo de columna ---------- */
                $colMeta = $this->detectColumnTypes($this->cols);

                foreach ($colMeta as $colIndex => $type) {
                    $letter = $this->indexToColumn($colIndex);

                    if ($lastDataRow < ($headerRow + 1)) continue;

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

                /* ---------- Tabla auxiliar para gráfica (Total general por día en MXN estimado) ---------- */
                if (!empty($this->dailyVentas)) {
                    $auxStartRow = $lastDataRow + 2;

                    $aux = [
                        ['Fecha (agrupada)', 'Total general por día (MXN estimado)'],
                    ];
                    foreach ($this->dailyVentas as $day) {
                        $aux[] = [$day['label'], $day['total']];
                    }

                    $sheet->fromArray($aux, null, 'A' . $auxStartRow);

                    $auxHeaderRange = "A{$auxStartRow}:B{$auxStartRow}";
                    $sheet->getStyle($auxHeaderRange)->getFont()->setBold(true)->setSize(10);
                    $sheet->getStyle($auxHeaderRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
                    $sheet->getStyle($auxHeaderRange)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    $auxEndRow = $auxStartRow + count($aux) - 1;
                    $auxRange  = "A{$auxStartRow}:B{$auxEndRow}";
                    $sheet->getStyle($auxRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

                    $sheet->getStyle("B" . ($auxStartRow + 1) . ":B{$auxEndRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');

                    $sheet->getStyle("A" . ($auxStartRow + 1) . ":A{$auxEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("B" . ($auxStartRow + 1) . ":B{$auxEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($auxStartRow)->setRowHeight(18);
                }

                /* ---------- Resumen de totales (debajo de la gráfica) ---------- */
                $totales = $this->meta['totales'] ?? null;
                if ($totales) {
                    $auxStartRow = $lastDataRow + 2;
                    $auxEndRow   = $auxStartRow + (empty($this->dailyVentas) ? 0 : (count($this->dailyVentas) + 1));
                    $chartBottom = $auxStartRow + 18;

                    $summaryStartRow = max($auxEndRow, $chartBottom) + 2;

                    $tc = $this->tipoCambio > 0 ? $this->tipoCambio : 0;

                    $get = function ($key, $cur) use ($totales) {
                        $k = $totales[$key] ?? null;
                        if (!$k) return 0.0;
                        return (float)($k[$cur] ?? 0);
                    };

                    $p_mxn = $get('productos', 'mxn');
                    $p_usd = $get('productos', 'usd');
                    $s_mxn = $get('servicios', 'mxn');
                    $s_usd = $get('servicios', 'usd');
                    $m_mxn = $get('materiales_no_previstos', 'mxn');
                    $m_usd = $get('materiales_no_previstos', 'usd');
                    $g_mxn = $get('general', 'mxn');
                    $g_usd = $get('general', 'usd');
                    $a_mxn = $get('anticipo', 'mxn');
                    $a_usd = $get('anticipo', 'usd');
                    $sl_mxn = $get('saldo', 'mxn');
                    $sl_usd = $get('saldo', 'usd');

                    $toMXN = fn($usd) => $tc ? ((float)$usd * $tc) : 0.0;

                    $p_total = $p_mxn + $toMXN($p_usd);
                    $s_total = $s_mxn + $toMXN($s_usd);
                    $m_total = $m_mxn + $toMXN($m_usd);
                    $g_total = $g_mxn + $toMXN($g_usd);
                    $a_total = $a_mxn + $toMXN($a_usd);
                    $sl_total = $sl_mxn + $toMXN($sl_usd);

                    $sheet->mergeCells("A{$summaryStartRow}:D{$summaryStartRow}");
                    $sheet->setCellValue("A{$summaryStartRow}", 'Resumen de totales (MXN / USD, estimados a MXN)');
                    $sheet->getStyle("A{$summaryStartRow}")->getFont()
                        ->setBold(true)->setSize(11)->getColor()->setARGB('FF0F172A');

                    $sheet->getStyle("A{$summaryStartRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $r = $summaryStartRow + 1;

                    $block = function (string $title, float $mxn, float $usd, float $total, int &$r) use ($sheet, $tc) {
                        $sheet->setCellValue("A{$r}", $title . " (MXN)");
                        $sheet->setCellValue("B{$r}", $mxn);
                        $r++;

                        $sheet->setCellValue("A{$r}", $title . " (USD)");
                        $sheet->setCellValue("B{$r}", $usd);
                        $r++;

                        $sheet->setCellValue("A{$r}", "Equivalente MXN del {$title} en USD (TC {$tc})");
                        $sheet->setCellValue("B{$r}", $tc ? ($usd * $tc) : 0);
                        $r++;

                        $sheet->setCellValue("A{$r}", "{$title} estimado en MXN");
                        $sheet->setCellValue("B{$r}", $total);
                        $r += 2;
                    };

                    $block('Total productos', $p_mxn, $p_usd, $p_total, $r);
                    $block('Total servicios', $s_mxn, $s_usd, $s_total, $r);
                    $block('Materiales no previstos', $m_mxn, $m_usd, $m_total, $r);
                    $block('Total general', $g_mxn, $g_usd, $g_total, $r);
                    $block('Anticipos', $a_mxn, $a_usd, $a_total, $r);
                    $block('Saldo', $sl_mxn, $sl_usd, $sl_total, $r);

                    $summaryRange = "A" . ($summaryStartRow + 1) . ":B" . ($r - 1);
                    $sheet->getStyle($summaryRange)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($summaryRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

                    $sheet->getStyle("A" . ($summaryStartRow + 1) . ":A" . ($r - 1))
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("B" . ($summaryStartRow + 1) . ":B" . ($r - 1))
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }

    public function charts()
    {
        if (empty($this->dailyVentas) || empty($this->cols)) {
            return [];
        }

        $sheetTitle  = $this->sheetTitle;
        $headerRow   = 5;
        $lastDataRow = $headerRow + count($this->rows);

        $auxStartRow   = $lastDataRow + 2;
        $auxHeaderRow  = $auxStartRow;
        $firstDataRow  = $auxStartRow + 1;
        $lastAuxRow    = $auxStartRow + count($this->dailyVentas);

        $labelCell = "'{$sheetTitle}'!\$B\${$auxHeaderRow}";
        $catRange  = "'{$sheetTitle}'!\$A\${$firstDataRow}:\$A\${$lastAuxRow}";
        $valRange  = "'{$sheetTitle}'!\$B\${$firstDataRow}:\$B\${$lastAuxRow}";

        $dataSeriesLabels = [
            new DataSeriesValues('String', $labelCell, null, 1),
        ];

        $xAxisTickValues = [
            new DataSeriesValues('String', $catRange, null, count($this->dailyVentas)),
        ];

        $dataSeriesValues = [
            new DataSeriesValues('Number', $valRange, null, count($this->dailyVentas)),
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
        $title    = new ChartTitle('Total general por día (MXN estimado)');

        $chart = new Chart(
            'ventas_por_dia',
            $title,
            $legend,
            $plotArea
        );

        $topRow    = $auxStartRow;
        $bottomRow = $topRow + 18;

        $chart->setTopLeftPosition('D' . $topRow);
        $chart->setBottomRightPosition('K' . $bottomRow);

        return [$chart];
    }

    protected function buildDailyVentas(): void
    {
        $idxFecha  = array_search('Fecha', $this->cols, true);
        $idxTotal  = array_search('Total general', $this->cols, true);

        // compatibilidad por si llega "Total orden"
        if ($idxTotal === false) {
            $idxTotal = array_search('Total orden', $this->cols, true);
        }

        $idxMoneda = array_search('Moneda', $this->cols, true);

        if ($idxFecha === false || $idxTotal === false) {
            $this->dailyVentas = [];
            return;
        }

        $tc = $this->tipoCambio > 0 ? $this->tipoCambio : 1.0;
        $daily = [];

        foreach ($this->rows as $row) {
            $fechaStr = $row[$idxFecha] ?? null;
            $totalStr = $row[$idxTotal] ?? null;

            if (!$fechaStr || $totalStr === null) continue;

            $moneda = 'MXN';
            if ($idxMoneda !== false) {
                $moneda = strtoupper(trim((string)($row[$idxMoneda] ?? 'MXN')));
            }

            $monto = (float) str_replace(
                ['US$', '$', ',', ' ', 'MXN', 'USD'],
                '',
                (string)$totalStr
            );

            if ($moneda === 'USD') {
                $monto = $monto * $tc;
            }

            try {
                $c = Carbon::createFromFormat('d/m/Y', $fechaStr);
                $key   = $c->format('Y-m-d');
                $label = $c->format('d/m/Y');
            } catch (\Exception $e) {
                $key   = $fechaStr;
                $label = $fechaStr;
            }

            if (!isset($daily[$key])) {
                $daily[$key] = ['label' => $label, 'total' => 0.0];
            }

            $daily[$key]['total'] += $monto;
        }

        ksort($daily);
        $this->dailyVentas = array_values($daily);
    }

    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            // moneda/importe/total/saldo/pagado/subtotal/iva/anticipo/costo/materiales -> #,##0.00
            if (
                str_contains($lc, 'total') ||
                str_contains($lc, 'importe') ||
                str_contains($lc, 'monto') ||
                str_contains($lc, 'precio') ||
                str_contains($lc, 'saldo') ||
                str_contains($lc, 'pagado') ||
                str_contains($lc, 'subtotal') ||
                str_contains($lc, 'iva') ||
                str_contains($lc, 'anticipo') ||
                str_contains($lc, 'costo') ||
                str_contains($lc, 'materiales')
            ) {
                $out[$i] = 'currency';
                continue;
            }

            if (str_contains($lc, 'cantidad') || str_contains($lc, 'cant') || str_contains($lc, 'num')) {
                $out[$i] = 'int';
                continue;
            }

            if (
                str_contains($lc, 'folio') ||
                str_contains($lc, 'fecha') ||
                str_contains($lc, 'moneda') ||
                str_contains($lc, 'estado') ||
                str_contains($lc, 'tipo') ||
                str_contains($lc, 'orden')
            ) {
                $out[$i] = 'center';
                continue;
            }

            $out[$i] = 'text';
        }

        return $out;
    }

    protected function indexToColumn(int $index): string
    {
        $index += 1;
        $column = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index = (int) floor(($index - 1) / 26);
        }

        return $column;
    }
}
