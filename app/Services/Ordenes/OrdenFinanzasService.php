<?php

namespace App\Services\Ordenes;

use App\Models\OrdenServicio;
use App\Models\PagoCredito;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OrdenFinanzasService
{
    /**
     * @param  EloquentCollection<int, OrdenServicio>|Collection<int, OrdenServicio>|array<int, OrdenServicio>  $ordenes
     * @return Collection<int, array<string, mixed>>
     */
    public function mapSnapshots(EloquentCollection|Collection|array $ordenes, Carbon|string|null $paymentsUntil = null): Collection
    {
        $ordenes = $ordenes instanceof EloquentCollection
            ? $ordenes
            : new EloquentCollection(collect($ordenes)->filter()->all());

        if ($ordenes->isEmpty()) {
            return collect();
        }

        $ordenes->loadMissing('productos');

        $clientIds = $ordenes->pluck('id_cliente')->filter()->unique()->values();
        $creditOrders = new EloquentCollection();

        if ($clientIds->isNotEmpty()) {
            $creditOrders = OrdenServicio::query()
                ->whereIn('id_cliente', $clientIds)
                ->where('tipo_pago', 'credito_cliente')
                ->with('productos')
                ->get()
                ->sortBy(fn (OrdenServicio $orden) => $this->creditSortKey($orden))
                ->values();
        }

        $allOrders = (new EloquentCollection(
            $ordenes->merge($creditOrders)->unique('id_orden_servicio')->values()->all()
        ));

        $allOrders->loadMissing('productos');

        $snapshots = [];
        foreach ($allOrders as $orden) {
            $snapshots[(int) $orden->getKey()] = $this->baseSnapshot($orden);
        }

        $paymentsByClient = $this->paymentsByClient($clientIds, $paymentsUntil);

        foreach ($creditOrders->groupBy('id_cliente') as $clientId => $ordersByClient) {
            $remainingPayments = (float) ($paymentsByClient[(int) $clientId] ?? 0);

            foreach ($ordersByClient as $orden) {
                $id = (int) $orden->getKey();

                if (!isset($snapshots[$id])) {
                    continue;
                }

                $creditBalanceMxn = (float) ($snapshots[$id]['saldo_credito_mxn'] ?? 0);
                $appliedCreditMxn = min(max($remainingPayments, 0), $creditBalanceMxn);
                $remainingPayments = max(round($remainingPayments - $appliedCreditMxn, 2), 0);

                $snapshots[$id] = $this->applyCreditPayments($snapshots[$id], $appliedCreditMxn);
            }
        }

        return $ordenes
            ->values()
            ->mapWithKeys(function (OrdenServicio $orden) use ($snapshots): array {
                $id = (int) $orden->getKey();

                return [$id => $snapshots[$id] ?? $this->baseSnapshot($orden)];
            });
    }

    /**
     * @param  Collection<int, int|string>  $clientIds
     * @return array<int, float>
     */
    protected function paymentsByClient(Collection $clientIds, Carbon|string|null $paymentsUntil = null): array
    {
        if ($clientIds->isEmpty()) {
            return [];
        }

        $cutoff = $this->normalizeCutoff($paymentsUntil);

        return PagoCredito::query()
            ->selectRaw('clave_cliente, COALESCE(SUM(monto), 0) as total_pagado')
            ->whereIn('clave_cliente', $clientIds->all())
            ->when($cutoff, fn ($query) => $query->whereDate('fecha', '<=', $cutoff->toDateString()))
            ->groupBy('clave_cliente')
            ->pluck('total_pagado', 'clave_cliente')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseSnapshot(OrdenServicio $orden): array
    {
        $currency = $this->normalizeCurrency($orden->moneda ?? 'MXN');
        $rate = $this->normalizeRate((float) ($orden->tasa_cambio ?? 1));

        $totalOrder = round((float) ($orden->total_final ?? 0), 2);
        $totalOrderMxn = $this->toMxn($totalOrder, $currency, $rate);

        $anticipoMxn = round((float) ($orden->anticipo_mxn ?? 0), 2);
        if ($anticipoMxn <= 0) {
            $anticipoMxn = $this->toMxn((float) ($orden->anticipo ?? 0), $currency, $rate);
        }

        $anticipoOrder = $this->fromMxn($anticipoMxn, $currency, $rate);
        $saldoCreditoMxn = max(round($totalOrderMxn - $anticipoMxn, 2), 0);
        $saldoCredito = $this->fromMxn($saldoCreditoMxn, $currency, $rate);

        $completed = $this->isCompletedStatus((string) ($orden->estado ?? ''));
        $isCredit = (string) ($orden->tipo_pago ?? '') === 'credito_cliente';

        $totalPaidMxn = $isCredit
            ? min($anticipoMxn, $totalOrderMxn)
            : ($completed ? $totalOrderMxn : min($anticipoMxn, $totalOrderMxn));

        $totalPaid = $this->fromMxn($totalPaidMxn, $currency, $rate);
        $outstandingMxn = max(round($totalOrderMxn - $totalPaidMxn, 2), 0);
        $outstanding = $this->fromMxn($outstandingMxn, $currency, $rate);

        return [
            'id' => (int) $orden->getKey(),
            'id_cliente' => (int) ($orden->id_cliente ?? 0),
            'tipo_pago' => (string) ($orden->tipo_pago ?? ''),
            'moneda' => $currency,
            'tasa_cambio' => $rate,
            'finalizada' => $completed,
            'total_orden' => $totalOrder,
            'total_orden_mxn' => $totalOrderMxn,
            'anticipo' => $anticipoOrder,
            'anticipo_mxn' => $anticipoMxn,
            'saldo_credito' => $saldoCredito,
            'saldo_credito_mxn' => $saldoCreditoMxn,
            'credito_aplicado' => 0.0,
            'credito_aplicado_mxn' => 0.0,
            'total_pagado' => $totalPaid,
            'total_pagado_mxn' => $totalPaidMxn,
            'saldo_cobro' => $outstanding,
            'saldo_cobro_mxn' => $outstandingMxn,
            'esta_pagada' => $outstandingMxn <= 0.01,
            'estado_pago' => $this->paymentStatusLabel($outstandingMxn, $totalPaidMxn),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function applyCreditPayments(array $snapshot, float $appliedCreditMxn): array
    {
        $appliedCreditMxn = round(max($appliedCreditMxn, 0), 2);

        $totalPaidMxn = min(
            (float) ($snapshot['total_orden_mxn'] ?? 0),
            round((float) ($snapshot['anticipo_mxn'] ?? 0) + $appliedCreditMxn, 2)
        );

        $outstandingMxn = max(round((float) ($snapshot['total_orden_mxn'] ?? 0) - $totalPaidMxn, 2), 0);
        $currency = (string) ($snapshot['moneda'] ?? 'MXN');
        $rate = (float) ($snapshot['tasa_cambio'] ?? 1);

        $snapshot['credito_aplicado_mxn'] = $appliedCreditMxn;
        $snapshot['credito_aplicado'] = $this->fromMxn($appliedCreditMxn, $currency, $rate);
        $snapshot['total_pagado_mxn'] = $totalPaidMxn;
        $snapshot['total_pagado'] = $this->fromMxn($totalPaidMxn, $currency, $rate);
        $snapshot['saldo_cobro_mxn'] = $outstandingMxn;
        $snapshot['saldo_cobro'] = $this->fromMxn($outstandingMxn, $currency, $rate);
        $snapshot['esta_pagada'] = $outstandingMxn <= 0.01;
        $snapshot['estado_pago'] = $this->paymentStatusLabel($outstandingMxn, $totalPaidMxn);

        return $snapshot;
    }

    protected function paymentStatusLabel(float $outstandingMxn, float $totalPaidMxn): string
    {
        if ($outstandingMxn <= 0.01) {
            return 'Pagado';
        }

        if ($totalPaidMxn > 0.01) {
            return 'Abonado';
        }

        return 'Pendiente';
    }

    protected function isCompletedStatus(string $status): bool
    {
        return in_array(
            mb_strtolower(trim($status)),
            ['finalizada', 'finalizado', 'completada', 'completado', 'pagada', 'pagado', 'cerrada', 'cerrado'],
            true
        );
    }

    protected function creditSortKey(OrdenServicio $orden): string
    {
        $base = $orden->fecha_orden
            ?? $orden->created_at
            ?? null;

        try {
            $date = $base ? Carbon::parse($base)->format('Y-m-d H:i:s') : '9999-12-31 23:59:59';
        } catch (\Throwable) {
            $date = '9999-12-31 23:59:59';
        }

        return $date . '|' . str_pad((string) $orden->getKey(), 10, '0', STR_PAD_LEFT);
    }

    protected function normalizeCutoff(Carbon|string|null $paymentsUntil = null): Carbon
    {
        if ($paymentsUntil instanceof Carbon) {
            return $paymentsUntil->copy()->endOfDay();
        }

        if (is_string($paymentsUntil) && trim($paymentsUntil) !== '') {
            return Carbon::parse($paymentsUntil)->endOfDay();
        }

        return Carbon::now()->endOfDay();
    }

    protected function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return in_array($currency, ['MXN', 'USD'], true) ? $currency : 'MXN';
    }

    protected function normalizeRate(float $rate): float
    {
        return $rate > 0 ? $rate : 1.0;
    }

    protected function toMxn(float $amount, string $currency, float $rate): float
    {
        return round($currency === 'USD' ? ($amount * $rate) : $amount, 2);
    }

    protected function fromMxn(float $amount, string $currency, float $rate): float
    {
        if ($currency === 'USD' && $rate > 0) {
            return round($amount / $rate, 2);
        }

        return round($amount, 2);
    }
}
