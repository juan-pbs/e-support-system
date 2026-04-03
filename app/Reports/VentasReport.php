<?php

namespace App\Reports;

use App\Models\OrdenServicio;
use App\Services\Ordenes\OrdenFinanzasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class VentasReport
{
    public function __construct(private OrdenFinanzasService $finanzas) {}

    public function build($desde = null, $hasta = null): array
    {
        $ordenTable = (new OrdenServicio())->getTable();
        $fechaCol = Schema::hasColumn($ordenTable, 'fecha_orden') ? 'fecha_orden' : 'created_at';

        $ordenes = OrdenServicio::query()
            ->with(['cliente', 'productos.producto'])
            ->when($desde, function ($query) use ($fechaCol, $desde) {
                if ($fechaCol === 'fecha_orden') {
                    $query->whereDate('fecha_orden', '>=', $this->toDateString($desde));
                    return;
                }

                $query->where($fechaCol, '>=', $this->toDateTimeString($desde, true));
            })
            ->when($hasta, function ($query) use ($fechaCol, $hasta) {
                if ($fechaCol === 'fecha_orden') {
                    $query->whereDate('fecha_orden', '<=', $this->toDateString($hasta));
                    return;
                }

                $query->where($fechaCol, '<=', $this->toDateTimeString($hasta, false));
            })
            ->orderBy($fechaCol)
            ->orderBy('id_orden_servicio')
            ->get()
            ->filter(fn (OrdenServicio $orden) => $this->isCompletedStatus((string) ($orden->estado ?? '')))
            ->values();

        $cutoff = $hasta instanceof Carbon
            ? $hasta->copy()->endOfDay()
            : ($hasta ? Carbon::parse((string) $hasta)->endOfDay() : Carbon::now()->endOfDay());

        $snapshots = $this->finanzas->mapSnapshots($ordenes, $cutoff);

        $rowsOut = [];
        $sum = [
            'productos' => ['MXN' => 0.0, 'USD' => 0.0],
            'servicios' => ['MXN' => 0.0, 'USD' => 0.0],
            'impuestos' => ['MXN' => 0.0, 'USD' => 0.0],
            'materiales_no_previstos' => ['MXN' => 0.0, 'USD' => 0.0],
            'general' => ['MXN' => 0.0, 'USD' => 0.0],
            'pagado' => ['MXN' => 0.0, 'USD' => 0.0],
            'anticipo' => ['MXN' => 0.0, 'USD' => 0.0],
            'saldo' => ['MXN' => 0.0, 'USD' => 0.0],
        ];

        foreach ($ordenes as $orden) {
            $snapshot = $snapshots->get((int) $orden->getKey(), []);

            $currency = $this->normalizeCurrency((string) ($orden->moneda ?? 'MXN'));
            $sign = $currency === 'USD' ? 'US$' : '$';

            $totalProductos = round((float) ($orden->materiales_total ?? 0), 2);
            $costoServicio = round((float) ($orden->precio ?? 0), 2);
            $costoOperativo = round((float) ($orden->costo_operativo ?? 0), 2);
            $totalServicios = round($costoServicio + $costoOperativo, 2);
            $impuestos = round((float) ($orden->impuestos ?? 0), 2);
            $materialesNP = round((float) ($orden->total_adicional ?? 0), 2);
            $totalOrden = round((float) ($snapshot['total_orden'] ?? $orden->total_final ?? 0), 2);
            $totalPagado = round((float) ($snapshot['total_pagado'] ?? 0), 2);
            $saldo = round((float) ($snapshot['saldo_cobro'] ?? 0), 2);

            $cliente = trim((string) (
                optional($orden->cliente)->nombre
                ?: optional($orden->cliente)->nombre_empresa
                ?: ''
            ));

            $numerosParte = $orden->productos
                ->map(fn ($detalle) => trim((string) ($detalle->producto?->numero_parte ?: $detalle->codigo_producto ?: '')))
                ->filter()
                ->unique()
                ->implode(', ');

            if ($numerosParte === '') {
                $numerosParte = '-';
            }

            $rowsOut[] = [
                'Fecha' => $this->formatDate($orden->{$fechaCol} ?? null),
                'Orden' => (int) $orden->id_orden_servicio,
                'Cliente' => $cliente !== '' ? $cliente : '-',
                'Tipo de orden' => $this->tipoOrdenLabel((string) ($orden->tipo_orden ?? '')),
                'Tipo de pago' => $this->tipoPagoLabel((string) ($orden->tipo_pago ?? '')),
                'Moneda' => $currency,
                'Estado' => (string) ($orden->estado ?? '-'),
                'Facturacion' => (int) ($orden->facturado ?? 0) === 1 ? 'Facturado' : 'No facturado',
                'Total productos' => $this->fmtMoneyNoSign($totalProductos),
                'Costo servicio' => $this->fmtMoneyNoSign($costoServicio),
                'Costo operativo' => $this->fmtMoneyNoSign($costoOperativo),
                'Total servicios' => $this->fmtMoneyNoSign($totalServicios),
                'Impuestos' => $this->fmtMoneyNoSign($impuestos),
                'Materiales no previstos' => $this->fmtMoneyNoSign($materialesNP),
                'Total orden' => $sign . $this->fmtMoneyNoSign($totalOrden),
                'Total pagado' => $sign . $this->fmtMoneyNoSign($totalPagado),
                'Saldo' => $this->fmtMoneyNoSign($saldo),
                'Números de parte' => $numerosParte,
            ];

            $sum['productos'][$currency] += $totalProductos;
            $sum['servicios'][$currency] += $totalServicios;
            $sum['impuestos'][$currency] += $impuestos;
            $sum['materiales_no_previstos'][$currency] += $materialesNP;
            $sum['general'][$currency] += $totalOrden;
            $sum['pagado'][$currency] += $totalPagado;
            $sum['anticipo'][$currency] += round((float) ($snapshot['anticipo'] ?? 0), 2);
            $sum['saldo'][$currency] += $saldo;
        }

        return [
            'cols' => [
                'Fecha',
                'Orden',
                'Cliente',
                'Tipo de orden',
                'Tipo de pago',
                'Moneda',
                'Estado',
                'Facturacion',
                'Total productos',
                'Costo servicio',
                'Costo operativo',
                'Total servicios',
                'Impuestos',
                'Materiales no previstos',
                'Total orden',
                'Total pagado',
                'Saldo',
                'Números de parte',
            ],
            'rows' => $rowsOut,
            'meta' => [
                'num_registros' => count($rowsOut),
                'totales' => [
                    'productos' => ['mxn' => $sum['productos']['MXN'], 'usd' => $sum['productos']['USD']],
                    'servicios' => ['mxn' => $sum['servicios']['MXN'], 'usd' => $sum['servicios']['USD']],
                    'impuestos' => ['mxn' => $sum['impuestos']['MXN'], 'usd' => $sum['impuestos']['USD']],
                    'materiales_no_previstos' => ['mxn' => $sum['materiales_no_previstos']['MXN'], 'usd' => $sum['materiales_no_previstos']['USD']],
                    'general' => ['mxn' => $sum['general']['MXN'], 'usd' => $sum['general']['USD']],
                    'pagado' => ['mxn' => $sum['pagado']['MXN'], 'usd' => $sum['pagado']['USD']],
                    'anticipo' => ['mxn' => $sum['anticipo']['MXN'], 'usd' => $sum['anticipo']['USD']],
                    'saldo' => ['mxn' => $sum['saldo']['MXN'], 'usd' => $sum['saldo']['USD']],
                ],
            ],
        ];
    }

    protected function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function isCompletedStatus(string $status): bool
    {
        return in_array(
            mb_strtolower(trim($status)),
            ['finalizada', 'finalizado', 'completada', 'completado', 'pagada', 'pagado', 'cerrada', 'cerrado'],
            true
        );
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return in_array($currency, ['MXN', 'USD'], true) ? $currency : 'MXN';
    }

    protected function fmtMoneyNoSign(float $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    protected function tipoOrdenLabel(string $tipo): string
    {
        return match ($tipo) {
            'compra' => 'Compra',
            'servicio_simple' => 'Servicio (simple)',
            'servicio_proyecto' => 'Servicio (proyecto)',
            default => $tipo !== '' ? $tipo : '-',
        };
    }

    protected function tipoPagoLabel(string $tipoPago): string
    {
        return match ($tipoPago) {
            'efectivo' => 'efectivo',
            'transferencia' => 'transferencia',
            'tarjeta' => 'tarjeta',
            'credito_cliente' => 'credito_cliente',
            default => $tipoPago !== '' ? $tipoPago : '-',
        };
    }

    protected function toDateString(mixed $value): string
    {
        return $value instanceof Carbon
            ? $value->toDateString()
            : Carbon::parse((string) $value)->toDateString();
    }

    protected function toDateTimeString(mixed $value, bool $start): string
    {
        if ($value instanceof Carbon) {
            return ($start ? $value->copy()->startOfDay() : $value->copy()->endOfDay())->toDateTimeString();
        }

        $carbon = Carbon::parse((string) $value);

        return ($start ? $carbon->startOfDay() : $carbon->endOfDay())->toDateTimeString();
    }
}
