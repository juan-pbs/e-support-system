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

class ClientesTopExport implements
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
    protected array $meta;

    /** Nombre de hoja */
    protected string $sheetTitle = 'Clientes';

    public function __construct(string $titulo, array $cols, array $rows, array $meta = [])
    {
        $this->titulo = $titulo;
        $this->cols   = $cols;
        $this->rows   = $rows;
        $this->meta   = $meta;
    }

    /* =================== Datos que se escriben en la hoja =================== */

    public function array(): array
    {
        $out = $this->rows;

        $imp = $this->meta['importe'] ?? null;

        // Mantenemos tu mecánica de RESUMEN, pero dejando valores NUMÉRICOS
        // para que el formato moneda se aplique visualmente (sin tocar datos base).
        if (is_array($imp)) {
            $colCount = count($this->cols);
            $blank = array_fill(0, $colCount, '');
            $out[] = $blank;

            $r0 = $blank; $r0[0] = 'RESUMEN';
            $out[] = $r0;

            $r1 = $blank; $r1[0] = 'Total MXN'; $r1[1] = (float)($imp['mxn'] ?? 0);
            $out[] = $r1;

            $r2 = $blank; $r2[0] = 'Total USD'; $r2[1] = (float)($imp['usd'] ?? 0);
            $out[] = $r2;

            $tc = (float)($imp['tipo_cambio'] ?? 0);
            $r3 = $blank;
            $r3[0] = 'Total estimado MXN' . ($tc ? " (TC {$tc})" : '');
            $r3[1] = (float)($imp['estimado_mxn'] ?? 0);
            $out[] = $r3;
        }

        return $out;
    }

    public function headings(): array
    {
        return $this->cols;
    }

    public function startCell(): string
    {
        // Igual que tu formato “master”: dejamos espacio para título/subtítulo/metadata
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

                // Si no hay columnas, igual pintamos título bonito
                if (empty($this->cols)) {
                    $sheet->mergeCells("A1:D1");
                    $sheet->setCellValue('A1', $this->titulo);
                    return;
                }

                $headerRow     = 5;
                $lastColIndex  = count($this->cols) - 1;
                $lastColLetter = $this->indexToColumn($lastColIndex);

                $dataCount     = count($this->array());
                $lastDataRow   = $headerRow + $dataCount; // encabezado en 5, data inicia en 6

                // ===== Ajustes generales =====
                $sheet->setShowGridlines(false);

                // Tamaños de filas (corporativo/compacto)
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Congelar encabezado (fila 5) -> data en fila 6
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

                /* ---------- Bordes + zebra + compactación ---------- */
                if ($lastDataRow >= $headerRow) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";

                    // Bordes suaves
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0'); // slate-200

                    // Alineación vertical en toda la tabla
                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Detectar fila "RESUMEN" (si existe) para NO cebrar esa parte
                    $summaryRow = null;
                    for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
                        $val = (string) $sheet->getCell("A{$r}")->getValue();
                        if (mb_strtoupper(trim($val)) === 'RESUMEN') {
                            $summaryRow = $r;
                            break;
                        }
                    }

                    // Data real termina antes del blank + RESUMEN
                    $dataEndRow = $summaryRow ? max($headerRow, $summaryRow - 2) : $lastDataRow;

                    // Zebra SOLO datos
                    for ($r = $headerRow + 1; $r <= $dataEndRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);

                        if (($r % 2) === 0) {
                            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC'); // slate-50
                        }
                    }

                    // Compactar resto (incluye resumen)
                    for ($r = $dataEndRow + 1; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);
                    }

                    // No wrap en data para compactar
                    $sheet->getStyle("A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()->setWrapText(false);

                    /* ---------- Formatos por tipo de columna (solo en DATA, no resumen) ---------- */
                    $colMeta = $this->detectColumnTypes($this->cols);

                    foreach ($colMeta as $colIndex => $type) {
                        $letter = $this->indexToColumn($colIndex);

                        // solo aplicar si hay al menos una fila de data
                        if ($dataEndRow < ($headerRow + 1)) continue;

                        $dataColRange = "{$letter}" . ($headerRow + 1) . ":{$letter}{$dataEndRow}";

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

                    /* ---------- Bloque RESUMEN destacado ---------- */
                    if ($summaryRow) {
                        // Fila RESUMEN
                        $sheet->getStyle("A{$summaryRow}:{$lastColLetter}{$summaryRow}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => '1D4ED8'], // azul
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color'    => ['rgb' => 'EAEFF7'],
                            ],
                        ]);

                        // Totales (hasta 3 filas después si existen)
                        $totStart = $summaryRow + 1;
                        $totEnd   = min($lastDataRow, $summaryRow + 3);

                        if ($totStart <= $totEnd) {
                            $sheet->getStyle("A{$totStart}:{$lastColLetter}{$totEnd}")->applyFromArray([
                                'font' => ['bold' => true],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'color'    => ['rgb' => 'F3F5F8'],
                                ],
                            ]);

                            // Col B a la derecha y con moneda (si existe)
                            if ($lastColIndex >= 1) {
                                $sheet->getStyle("B{$totStart}:B{$totEnd}")
                                    ->getAlignment()
                                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                                // Formato moneda para RESUMEN (B)
                                $sheet->getStyle("B{$totStart}:B{$totEnd}")
                                    ->getNumberFormat()
                                    ->setFormatCode('#,##0.00');
                            }
                        }
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

            if (
                str_contains($lc, 'total') ||
                str_contains($lc, 'importe') ||
                str_contains($lc, 'monto') ||
                str_contains($lc, 'precio') ||
                str_contains($lc, 'venta') ||
                str_contains($lc, 'pago')
            ) {
                $out[$i] = 'currency';
                continue;
            }

            if (
                str_contains($lc, 'cantidad') ||
                str_contains($lc, 'num') ||
                str_contains($lc, 'conteo') ||
                str_contains($lc, 'orden')
            ) {
                $out[$i] = 'int';
                continue;
            }

            if (
                str_contains($lc, 'folio') ||
                str_contains($lc, 'fecha') ||
                str_contains($lc, 'vigenc') ||
                str_contains($lc, 'moneda') ||
                str_contains($lc, 'estado')
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
