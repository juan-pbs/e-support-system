<?php

namespace App\Exports\Reportes;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
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

class StockCriticoExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    WithEvents,
    WithCustomStartCell,
    ShouldAutoSize
{
    use Exportable;

    /** Branding */
    protected string $brandName = 'Sistema E-Support';

    protected string $titulo;
    protected array $cols;
    protected array $rows;

    protected string $sheetTitle;

    /**
     * @param string $titulo  Texto del título (ej. "Stock Crítico")
     * @param array  $cols    Encabezados de la tabla
     * @param array  $rows    Filas como ARREGLOS NUMÉRICOS, en el mismo orden que $cols
     */
    public function __construct(string $titulo, array $cols, array $rows)
    {
        $this->titulo = $titulo;
        $this->cols   = $cols;
        $this->rows   = $rows;

        // Nombre de la hoja
        $this->sheetTitle = 'Productos';
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

                // ===== Ajustes generales =====
                $sheet->setShowGridlines(false);

                // Filas corporativas
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(5)->setRowHeight(20);

                // Congelar encabezado (fila 5) -> empieza data en fila 6
                $sheet->freezePane('A6');

                if (empty($this->cols)) {
                    // Título bonito aun sin cols
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

                // AutoFiltro
                $sheet->setAutoFilter($headerRange);

                /* ---------- Bordes + zebra + compactación ---------- */
                if ($lastDataRow >= $headerRow) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";

                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0'); // slate-200

                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Zebra (solo data)
                    for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);

                        if (($r % 2) === 0) {
                            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC'); // slate-50
                        }
                    }

                    // Compacto (sin wrap en data)
                    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()->setWrapText(false);
                }

                /* ---------- Formatos por tipo de columna (visual) ---------- */
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
            },
        ];
    }

    /* ======================= Helpers internos ========================== */

    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $i => $c) {
            $lc = mb_strtolower(trim((string)$c));

            // dinero / importes
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

            // cantidades / contadores
            if (
                str_contains($lc, 'cantidad') ||
                str_contains($lc, 'cant') ||
                str_contains($lc, 'piez') ||
                str_contains($lc, 'stock') ||
                str_contains($lc, 'num')
            ) {
                $out[$i] = 'int';
                continue;
            }

            // centradas típicas
            if (
                str_contains($lc, 'folio') ||
                str_contains($lc, 'fecha') ||
                str_contains($lc, 'hora') ||
                str_contains($lc, 'moneda') ||
                str_contains($lc, 'estado') ||
                str_contains($lc, 'tipo') ||
                str_contains($lc, 'codigo') ||
                str_contains($lc, 'clave')
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
