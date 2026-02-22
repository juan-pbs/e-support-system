<?php

namespace App\Exports\Reportes;

use App\Reports\SalidasInventarioReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SalidasInventarioExport implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithEvents,
    WithCustomStartCell,
    ShouldAutoSize
{
    use Exportable;

    protected string $brandName  = 'Sistema E-Support';
    protected string $titulo     = 'Salidas de Inventario (Productos)';
    protected string $sheetTitle = 'Salidas';

    protected array $cols = [];
    protected array $rows = [];

    public function __construct($desde = null, $hasta = null)
    {
        $report = (new SalidasInventarioReport())->build($desde, $hasta);

        $this->cols = $this->uniqueCols($report['cols'] ?? []);
        $this->rows = $report['rows'] ?? [];
    }

    public function collection(): Collection
    {
        $cols = $this->cols;

        return collect($this->rows)->map(function ($row) use ($cols) {
            $out = [];

            foreach ($cols as $c) {
                $val = $row[$c] ?? '';

                if ($c === 'Cantidad') {
                    $out[] = (int)($val ?? 0);
                    continue;
                }

                if ($c === 'Precio unitario' || $c === 'Total') {
                    $out[] = $this->toNumber($val);
                    continue;
                }

                $out[] = $val;
            }

            return $out;
        });
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

                // Título
                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', $this->titulo);

                $sheet->getStyle("A1:{$lastColLetter}1")->getFont()
                    ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');

                $sheet->getStyle("A1:{$lastColLetter}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Subtítulo
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

                // Encabezados
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

                // Bordes + zebra
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

                // Formatos por columna
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

                // ✅ TOTALES (debajo de la tabla)
                $idxCantidad = array_search('Cantidad', $this->cols, true);
                $idxTotal    = array_search('Total', $this->cols, true);
                $idxMoneda   = array_search('Moneda', $this->cols, true);

                $sumCantidad = 0;
                $sumMXN      = 0.0;
                $sumUSD      = 0.0;

                foreach ($this->rows as $r) {
                    $sumCantidad += (int)($r['Cantidad'] ?? 0);

                    $mon = strtoupper(trim((string)($r['Moneda'] ?? 'MXN')));
                    $tot = $this->toNumber($r['Total'] ?? 0);

                    if ($mon === 'USD') $sumUSD += $tot;
                    else $sumMXN += $tot;
                }

                $summaryRow = $lastDataRow + 2;

                // Título totales
                $sheet->mergeCells("A{$summaryRow}:B{$summaryRow}");
                $sheet->setCellValue("A{$summaryRow}", "Totales");

                $sheet->getStyle("A{$summaryRow}:B{$summaryRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$summaryRow}:B{$summaryRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

                // Filas totales
                $r1 = $summaryRow + 1;

                $sheet->setCellValue("A{$r1}", "Total cantidad");
                $sheet->setCellValue("B{$r1}", $sumCantidad);

                $sheet->setCellValue("A" . ($r1 + 1), "Total MXN");
                $sheet->setCellValue("B" . ($r1 + 1), $sumMXN);

                $sheet->setCellValue("A" . ($r1 + 2), "Total USD");
                $sheet->setCellValue("B" . ($r1 + 2), $sumUSD);

                $range = "A{$r1}:B" . ($r1 + 2);
                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

                $sheet->getStyle("A{$r1}:A" . ($r1 + 2))->getFont()->setBold(true);
                $sheet->getStyle("B{$r1}")->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle("B" . ($r1 + 1) . ":B" . ($r1 + 2))->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->getStyle("B{$r1}:B" . ($r1 + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            if (
                str_contains($lc, 'numero de parte') ||
                str_contains($lc, 'numeros de serie') ||
                str_contains($lc, 'nombre producto')
            ) {
                $out[$i] = 'text';
                continue;
            }

            if (
                str_contains($lc, 'total') ||
                str_contains($lc, 'importe') ||
                str_contains($lc, 'monto') ||
                str_contains($lc, 'precio') ||
                str_contains($lc, 'costo')
            ) {
                $out[$i] = 'currency';
                continue;
            }

            if (str_contains($lc, 'cantidad') || str_contains($lc, 'id ')) {
                $out[$i] = 'int';
                continue;
            }

            if (str_contains($lc, 'fecha') || str_contains($lc, 'hora') || str_contains($lc, 'moneda')) {
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
            $mod    = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index  = (int) floor(($index - 1) / 26);
        }

        return $column;
    }

    protected function toNumber($value): float
    {
        if ($value === null) return 0.0;

        $s = trim((string)$value);
        if ($s === '' || $s === '—' || $s === '-') return 0.0;

        $s = str_replace([',', ' ', 'MXN', 'USD', '$', 'US$'], '', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    protected function uniqueCols(array $cols): array
    {
        $seen = [];
        $out  = [];
        foreach ($cols as $c) {
            $k = $this->norm($c);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $c;
        }
        return $out;
    }

    protected function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);
        return $s;
    }
}
