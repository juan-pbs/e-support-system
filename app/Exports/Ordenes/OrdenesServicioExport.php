<?php

namespace App\Exports\Ordenes;

use App\Models\OrdenServicio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenesServicioExport implements
    FromArray,
    ShouldAutoSize,
    WithCustomStartCell,
    WithEvents,
    WithHeadings,
    WithTitle
{
    use Exportable;

    protected string $brandName = 'Sistema E-Support';
    protected string $sheetTitle = 'Ordenes OS';

    /** @var array<int, array<int, mixed>> */
    protected array $rows;

    public function __construct(
        private Collection $ordenes,
        private Carbon $desde,
        private Carbon $hasta,
    ) {
        $this->rows = $this->buildRows();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Folio',
            'ID orden',
            'Fecha orden',
            'Creada el',
            'Actualizada el',
            'Cliente',
            'Empresa',
            'Tipo de orden',
            'Estado',
            'Prioridad',
            'Tecnico(s)',
            'Servicio',
            'Resumen del servicio',
            'Descripcion adicional',
            'Notas internas',
            'Tipo de pago',
            'Moneda',
            'Tasa de cambio',
            'Total materiales',
            'Costo servicio',
            'Costo operativo',
            'Impuestos',
            'Total adicional MXN',
            'Total adicional orden',
            'Anticipo',
            'Anticipo %',
            'Total final',
            'Saldo pendiente',
            'Facturacion',
            'Acta estado',
            'Acta firmada el',
        ];
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

                $cols = $this->headings();
                $headerRow = 5;
                $dataStartRow = $headerRow + 1;
                $lastColIndex = count($cols) - 1;
                $lastColLetter = $this->indexToColumn($lastColIndex);
                $lastDataRow = $headerRow + count($this->rows);

                $sheet->setShowGridlines(false);
                $sheet->freezePane('A6');

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(3)->setRowHeight(18);
                $sheet->getRowDimension(4)->setRowHeight(8);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', 'Reporte de ordenes de servicio');
                $sheet->getStyle("A1:{$lastColLetter}1")->getFont()
                    ->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
                $sheet->getStyle("A1:{$lastColLetter}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->setCellValue(
                    'A2',
                    $this->brandName . ' - Exportado el ' . Carbon::now()->format('d/m/Y H:i')
                );
                $sheet->getStyle("A2:{$lastColLetter}2")->getFont()
                    ->setSize(10)->getColor()->setARGB('FF334155');
                $sheet->getStyle("A2:{$lastColLetter}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle("A2:{$lastColLetter}2")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->mergeCells("A3:{$lastColLetter}3");
                $sheet->setCellValue('A3', $this->summaryLine());
                $sheet->getStyle("A3:{$lastColLetter}3")->getFont()
                    ->setBold(true)->setSize(10)->getColor()->setARGB('FF1E3A8A');
                $sheet->getStyle("A3:{$lastColLetter}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
                $sheet->getStyle("A3:{$lastColLetter}3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

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

                $tableRange = "A{$headerRow}:{$lastColLetter}{$lastDataRow}";
                $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFE2E8F0');
                $sheet->getStyle($tableRange)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                if ($lastDataRow >= $dataStartRow) {
                    for ($row = $dataStartRow; $row <= $lastDataRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(18);

                        if (($row % 2) === 0) {
                            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }

                    $sheet->getStyle("A{$dataStartRow}:{$lastColLetter}{$lastDataRow}")
                        ->getAlignment()
                        ->setWrapText(false);

                    foreach ($this->detectColumnTypes($cols) as $colIndex => $type) {
                        $letter = $this->indexToColumn($colIndex);
                        $dataColRange = "{$letter}{$dataStartRow}:{$letter}{$lastDataRow}";

                        if ($type === 'currency') {
                            $sheet->getStyle($dataColRange)->getNumberFormat()->setFormatCode('#,##0.00');
                            $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            continue;
                        }

                        if ($type === 'int') {
                            $sheet->getStyle($dataColRange)->getNumberFormat()->setFormatCode('0');
                            $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            continue;
                        }

                        if ($type === 'center') {
                            $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            continue;
                        }

                        $sheet->getStyle($dataColRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }
            },
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildRows(): array
    {
        return $this->ordenes->map(function (OrdenServicio $orden): array {
            $cliente = $orden->cliente;

            return [
                (string) ($orden->folio ?? ''),
                (int) ($orden->id_orden_servicio ?? 0),
                $this->formatDateValue($orden->fecha_orden, 'Y-m-d'),
                $this->formatDateValue($orden->created_at, 'Y-m-d H:i:s'),
                $this->formatDateValue($orden->updated_at, 'Y-m-d H:i:s'),
                trim((string) ($cliente?->nombre ?? '')),
                trim((string) ($cliente?->nombre_empresa ?? '')),
                $this->tipoOrdenLabel((string) $orden->tipo_orden),
                (string) ($orden->estado ?? ''),
                (string) ($orden->prioridad ?? ''),
                $this->tecnicosLabel($orden),
                (string) ($orden->servicio ?? ''),
                (string) ($orden->descripcion_servicio ?? ''),
                (string) ($orden->descripcion ?? ''),
                (string) ($orden->condiciones_generales ?? ''),
                (string) ($orden->tipo_pago ?? ''),
                strtoupper((string) ($orden->moneda ?? 'MXN')),
                (float) ($orden->tasa_cambio ?? 1),
                (float) ($orden->materiales_total ?? 0),
                (float) ($orden->precio ?? 0),
                (float) ($orden->costo_operativo ?? 0),
                (float) ($orden->impuestos ?? 0),
                (float) ($orden->total_adicional_mxn ?? 0),
                (float) ($orden->total_adicional ?? 0),
                (float) ($orden->anticipo ?? 0),
                (float) ($orden->anticipo_pct_calculado ?? 0),
                (float) ($orden->total_final ?? 0),
                (float) ($orden->saldo_pendiente ?? 0),
                (string) $orden->facturacion_label,
                (string) ($orden->acta_estado ?? ''),
                $this->formatDateValue($orden->acta_firmada_at, 'Y-m-d H:i:s'),
            ];
        })->all();
    }

    protected function summaryLine(): string
    {
        $total = $this->ordenes->count();
        $facturadas = $this->ordenes->sum(fn (OrdenServicio $orden) => (int) ((bool) ($orden->facturado ?? false)));
        $noFacturadas = max($total - $facturadas, 0);

        return sprintf(
            'Rango: %s al %s | Total OS: %d | Facturadas: %d | No facturadas: %d',
            $this->desde->format('d/m/Y'),
            $this->hasta->format('d/m/Y'),
            $total,
            $facturadas,
            $noFacturadas,
        );
    }

    protected function formatDateValue(mixed $value, string $format): string
    {
        if (blank($value)) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format($format);
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function tecnicosLabel(OrdenServicio $orden): string
    {
        if ((string) $orden->tipo_orden === 'compra') {
            return 'No aplica';
        }

        $names = $orden->tecnicos->pluck('name')->filter()->values();
        if ($names->isNotEmpty()) {
            return $names->implode(', ');
        }

        return (string) ($orden->tecnico?->name ?? 'Sin asignar');
    }

    protected function tipoOrdenLabel(string $tipo): string
    {
        return match ($tipo) {
            'compra' => 'Compra',
            'servicio_simple' => 'Servicio (simple)',
            'servicio_proyecto' => 'Servicio (proyecto)',
            default => $tipo,
        };
    }

    /**
     * @param  array<int, string>  $cols
     * @return array<int, string>
     */
    protected function detectColumnTypes(array $cols): array
    {
        $out = [];

        foreach ($cols as $index => $column) {
            $label = mb_strtolower(trim($column));

            if (
                str_contains($label, 'total') ||
                str_contains($label, 'costo') ||
                str_contains($label, 'saldo') ||
                str_contains($label, 'anticipo') ||
                str_contains($label, 'impuesto') ||
                str_contains($label, 'tasa')
            ) {
                $out[$index] = 'currency';
                continue;
            }

            if (
                str_starts_with($label, 'id ') ||
                $label === 'id'
            ) {
                $out[$index] = 'int';
                continue;
            }

            if (
                str_contains($label, 'folio') ||
                str_contains($label, 'fecha') ||
                str_contains($label, 'tipo') ||
                str_contains($label, 'estado') ||
                str_contains($label, 'moneda') ||
                str_contains($label, 'prioridad') ||
                str_contains($label, 'facturacion')
            ) {
                $out[$index] = 'center';
                continue;
            }

            $out[$index] = 'text';
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
