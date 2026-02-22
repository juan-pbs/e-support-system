<?php

namespace App\Reports;

use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TecnicosTopReport
{
    public function build($desde = null, $hasta = null): array
    {
        $osTable   = (new OrdenServicio)->getTable();
        $userTable = (new User)->getTable();
        $osCols    = Schema::getColumnListing($osTable);

        // Columna de importe (opcional)
        $sumCol = collect(['total', 'total_general', 'importe_total', 'monto_total', 'precio'])
            ->first(fn($c) => in_array($c, $osCols));

        // Columna de moneda (opcional)
        $monedaCol = collect(['moneda', 'moneda_pago', 'currency', 'divisa'])
            ->first(fn($c) => in_array($c, $osCols));

        $rowsDb      = collect();
        $hasImporte  = $sumCol !== null;
        $hasMoneda   = $hasImporte && $monedaCol !== null;

        /* ==========================================================
           CASO 1: la tabla orden_servicio tiene columna id_tecnico
           ========================================================== */
        if (in_array('id_tecnico', $osCols)) {
            $q = DB::table("$osTable as os")
                ->join("$userTable as u", 'u.id', '=', 'os.id_tecnico');

            // filtro por rango de fechas
            $this->spanWhere($q, $desde, $hasta, 'os.created_at');

            $select = [
                'u.name as tecnico',
                DB::raw('COUNT(*) as ordenes'),
            ];

            if ($hasImporte) {
                if ($hasMoneda) {
                    // Sumamos separado por moneda
                    $select[] = DB::raw(
                        "SUM(CASE WHEN UPPER(os.$monedaCol) = 'MXN' THEN os.$sumCol ELSE 0 END) as importe_mxn"
                    );
                    $select[] = DB::raw(
                        "SUM(CASE WHEN UPPER(os.$monedaCol) = 'USD' THEN os.$sumCol ELSE 0 END) as importe_usd"
                    );
                } else {
                    $select[] = DB::raw("SUM(os.$sumCol) as importe");
                }
            }

            $rowsDb = $q->select($select)
                ->groupBy('u.name')
                ->orderBy('u.name')   // todos los técnicos, orden alfabético
                ->get();

        } else {
            /* ==========================================================
               CASO 2: relación por tabla pivote orden_servicio_tecnico(s)
               ========================================================== */
            $pivot = Schema::hasTable('orden_servicio_tecnico')
                ? 'orden_servicio_tecnico'
                : (Schema::hasTable('orden_servicio_tecnicos')
                    ? 'orden_servicio_tecnicos'
                    : null);

            if (!$pivot) {
                return [
                    'cols'  => $hasImporte ? ['Técnico', 'Órdenes', 'Importe'] : ['Técnico', 'Órdenes'],
                    'rows'  => [],
                    'chart' => [],
                    'meta'  => [],
                ];
            }

            $pCols       = Schema::getColumnListing($pivot);
            $colUser     = in_array('user_id', $pCols) ? 'user_id'
                            : (in_array('id_tecnico', $pCols) ? 'id_tecnico' : 'user_id');
            $colOsPivot  = in_array('orden_servicio_id', $pCols) ? 'orden_servicio_id'
                            : (in_array('id_orden_servicio', $pCols) ? 'id_orden_servicio' : 'orden_servicio_id');

            // PK de orden_servicio
            $osPk = in_array('id_orden_servicio', $osCols)
                ? 'id_orden_servicio'
                : (in_array('id', $osCols) ? 'id' : $osCols[0]);

            $q = DB::table("$pivot as t")
                ->join("$osTable as os", "os.$osPk", '=', "t.$colOsPivot")
                ->join("$userTable as u", 'u.id', '=', "t.$colUser");

            $this->spanWhere($q, $desde, $hasta, 'os.created_at');

            $select = [
                'u.name as tecnico',
                DB::raw('COUNT(*) as ordenes'),
            ];

            if ($hasImporte) {
                if ($hasMoneda) {
                    $select[] = DB::raw(
                        "SUM(CASE WHEN UPPER(os.$monedaCol) = 'MXN' THEN os.$sumCol ELSE 0 END) as importe_mxn"
                    );
                    $select[] = DB::raw(
                        "SUM(CASE WHEN UPPER(os.$monedaCol) = 'USD' THEN os.$sumCol ELSE 0 END) as importe_usd"
                    );
                } else {
                    $select[] = DB::raw("SUM(os.$sumCol) as importe");
                }
            }

            $rowsDb = $q->select($select)
                ->groupBy('u.name')
                ->orderBy('u.name')
                ->get();
        }

        /* =========================
           Construimos filas de salida
           ========================== */

        // Tipo de cambio USD -> MXN (si lo necesitamos)
        $tipoCambio = $hasMoneda ? $this->obtenerTipoCambioUsdMxn() : 1.0;

        // encabecados
        if ($hasMoneda) {
            $cols = ['Técnico', 'Órdenes', 'Importe en MXN', 'Importe en USD', 'Importe estimado en MXN'];
        } elseif ($hasImporte) {
            $cols = ['Técnico', 'Órdenes', 'Importe'];
        } else {
            $cols = ['Técnico', 'Órdenes'];
        }

        $rows             = [];
        $totalMXN         = 0.0;
        $totalUSD         = 0.0;
        $totalEstimadoMXN = 0.0;

        foreach ($rowsDb as $r) {
            $fila = [
                'Técnico' => $r->tecnico,
                'Órdenes' => (int) $r->ordenes,
            ];

            if ($hasMoneda) {
                $mxn = (float) ($r->importe_mxn ?? 0);
                $usd = (float) ($r->importe_usd ?? 0);
                $estimado = $mxn + ($usd * $tipoCambio);

                $fila['Importe en MXN']          = '$' . number_format($mxn, 2);
                $fila['Importe en USD']          = '$' . number_format($usd, 2);
                $fila['Importe estimado en MXN'] = '$' . number_format($estimado, 2);

                $totalMXN         += $mxn;
                $totalUSD         += $usd;
                $totalEstimadoMXN += $estimado;

            } elseif ($hasImporte && isset($r->importe)) {
                $importe = (float) $r->importe;
                $fila['Importe'] = '$' . number_format($importe, 2);
                $totalMXN += $importe; // lo contamos como MXN por defecto
            }

            $rows[] = $fila;
        }

        // Datos para posible gráfica (por órdenes)
        $chartVals = collect($rowsDb)->map(fn($r) => (int) $r->ordenes)->all();
        $heights   = $this->scaleBars($chartVals);
        $bars      = [];

        foreach ($rowsDb as $i => $r) {
            $bars[] = [
                'label' => mb_substr($r->tecnico, 0, 3),
                'h'     => $heights[$i] ?? 10,
            ];
        }

        /* ====== META: totales de importe por moneda (MXN / USD) ====== */

        $metaImporte = null;

        if ($hasMoneda) {
            $metaImporte = [
                'mxn'          => $totalMXN,
                'usd'          => $totalUSD,
                'estimado_mxn' => $totalEstimadoMXN,
                'tipo_cambio'  => $tipoCambio,
            ];
        } elseif ($hasImporte) {
            $metaImporte = [
                'mxn'          => $totalMXN,
                'usd'          => 0.0,
                'estimado_mxn' => $totalMXN,
                'tipo_cambio'  => 1.0,
            ];
        }

        return [
            'cols'  => $cols,
            'rows'  => $rows,
            'chart' => $bars,
            'meta'  => [
                'importe' => $metaImporte,
            ],
        ];
    }

    protected function spanWhere($query, $desde, $hasta, $col = 'created_at')
    {
        if ($desde) {
            $query->where($col, '>=', $desde);
        }
        if ($hasta) {
            $query->where($col, '<=', $hasta);
        }
        return $query;
    }

    protected function scaleBars(array $values): array
    {
        $max = max($values ?: [1]);

        return array_map(function ($v) use ($max) {
            $h = $max > 0 ? ($v / $max) * 90 : 10;
            return (int) max(5, min(95, round($h)));
        }, $values);
    }

    /**
     * Obtiene tipo de cambio USD -> MXN (cuántos MXN es 1 USD).
     * Usa la misma lógica básica que el controlador de reportes.
     */
    protected function obtenerTipoCambioUsdMxn(): float
    {
        return Cache::remember('reportes.tecnicos.tipo_cambio_usd_mxn', 60 * 60 * 24, function () {
            $apiKey = config('services.exchange_rate.key');
            $fallback = 18.0; // valor por defecto

            if (!$apiKey) {
                return $fallback;
            }

            try {
                $response = Http::timeout(10)->get(
                    "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD"
                );

                if (!$response->successful()) {
                    return $fallback;
                }

                $data  = $response->json();
                $rates = $data['conversion_rates'] ?? [];
                $mxn   = $rates['MXN'] ?? null;

                return $mxn ?: $fallback;
            } catch (\Throwable $e) {
                return $fallback;
            }
        });
    }
}
