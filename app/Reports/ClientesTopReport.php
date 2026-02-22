<?php

namespace App\Reports;

use App\Models\OrdenServicio;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class ClientesTopReport
{
    public function build($desde = null, $hasta = null): array
    {
        $osTable  = (new OrdenServicio)->getTable(); // orden_servicio
        $cliTable = (new Cliente)->getTable();       // cliente

        $osCols  = Schema::getColumnListing($osTable);
        $cliCols = Schema::getColumnListing($cliTable);

        // ===== FK orden_servicio -> cliente
        $osCliFk = null;
        if (in_array('id_cliente', $osCols, true)) {
            $osCliFk = 'id_cliente';
        } elseif (in_array('clave_cliente', $osCols, true)) {
            $osCliFk = 'clave_cliente';
        } elseif (in_array('cliente_id', $osCols, true)) {
            $osCliFk = 'cliente_id';
        }

        // ===== PK cliente
        $cliPk = null;
        if (in_array('clave_cliente', $cliCols, true)) {
            $cliPk = 'clave_cliente';
        } elseif (in_array('id', $cliCols, true)) {
            $cliPk = 'id';
        }

        // ===== nombre cliente
        if (in_array('nombre', $cliCols, true)) {
            $cliName = 'nombre';
        } elseif (in_array('nombre_cliente', $cliCols, true)) {
            $cliName = 'nombre_cliente';
        } else {
            $cliName = $cliPk ?: 'id';
        }

        if (!$osCliFk || !$cliPk) {
            return [
                'cols'  => ['Cliente', 'Órdenes', 'Monto MXN', 'Monto USD', 'Total MXN'],
                'rows'  => [],
                'chart' => [],
                'meta'  => [],
            ];
        }

        // ===== columna fecha
        $dateCol = in_array('fecha_orden', $osCols, true) ? 'fecha_orden' : 'created_at';

        // ===== columna moneda (opcional)
        $monedaCol = collect(['moneda', 'moneda_pago', 'currency', 'divisa'])
            ->first(fn($c) => in_array($c, $osCols, true));

        // ===== importe (precio + impuestos) con fallback
        $hasPrecio    = in_array('precio', $osCols, true);
        $hasImpuestos = in_array('impuestos', $osCols, true);

        if ($hasPrecio) {
            $importeExpr = $hasImpuestos
                ? DB::raw('SUM(COALESCE(os.precio,0) + COALESCE(os.impuestos,0)) as importe')
                : DB::raw('SUM(COALESCE(os.precio,0)) as importe');
        } else {
            $sumCol = collect(['total', 'total_general', 'importe_total', 'monto_total'])
                ->first(fn($c) => in_array($c, $osCols, true));

            $importeExpr = $sumCol
                ? DB::raw("SUM(os.$sumCol) as importe")
                : DB::raw('SUM(0) as importe');
        }

        // ===== query base
        $q = DB::table("$osTable as os")
            ->join("$cliTable as c", "c.$cliPk", '=', "os.$osCliFk");

        if (in_array('tipo_orden', $osCols, true)) {
            $q->whereIn('os.tipo_orden', ['compra', 'servicio_simple', 'servicio_proyecto']);
        }

        $this->spanWhere($q, $desde, $hasta, "os.$dateCol");

        $select = [
            "c.$cliName as cliente",
            DB::raw("COUNT(*) as ordenes"),
            $importeExpr,
        ];

        if ($monedaCol) {
            $select[] = DB::raw("UPPER(os.$monedaCol) as moneda");
        } else {
            $select[] = DB::raw("'MXN' as moneda");
        }

        $groupBy = ["c.$cliPk", "c.$cliName"];
        $groupBy[] = $monedaCol ? DB::raw("UPPER(os.$monedaCol)") : DB::raw("'MXN'");

        $rowsDb = $q->select($select)->groupBy($groupBy)->get();

        // ===== TC USD->MXN (igual que tu reporte de técnicos)
        $tipoCambio = (float) Cache::get('reportes.tipo_cambio_usd_mxn', 18.0);

        // ===== acumular por cliente (MXN y USD separados)
        $byCliente = [];
        $totMXN = 0.0;
        $totUSD = 0.0;

        foreach ($rowsDb as $r) {
            $cliente = (string) ($r->cliente ?? 'N/A');
            $ordenes = (int) ($r->ordenes ?? 0);
            $importe = (float) ($r->importe ?? 0);
            $cur     = strtoupper(trim((string) ($r->moneda ?? 'MXN')));

            $isUSD = in_array($cur, ['USD','US$','DOLAR','DÓLAR','DOLLAR','DLLS','DLS','DL'], true);

            if (!isset($byCliente[$cliente])) {
                $byCliente[$cliente] = [
                    'ordenes' => 0,
                    'mxn' => 0.0,
                    'usd' => 0.0,
                ];
            }

            $byCliente[$cliente]['ordenes'] += $ordenes;

            if ($isUSD) {
                $byCliente[$cliente]['usd'] += $importe;
                $totUSD += $importe;
            } else {
                $byCliente[$cliente]['mxn'] += $importe;
                $totMXN += $importe;
            }
        }

        // ===== filas de salida (mostrando MXN, USD y Total MXN)
        $outRows = [];
        $chartVals = [];

        foreach ($byCliente as $cliente => $d) {
            $totalMXN = (float)$d['mxn'] + ((float)$d['usd'] * $tipoCambio);
            $chartVals[] = $totalMXN;

            $outRows[] = [
                'Cliente'   => $cliente,
                'Órdenes'   => (int) $d['ordenes'],
                'Monto MXN' => '$' . number_format((float)$d['mxn'], 2, '.', ','),
                'Monto USD' => '$' . number_format((float)$d['usd'], 2, '.', ','),
                'Total MXN' => '$' . number_format((float)$totalMXN, 2, '.', ','),
            ];
        }

        // Ordenar por Total MXN desc
        usort($outRows, function ($a, $b) {
            $na = (float) str_replace([',', '$'], '', (string)($a['Total MXN'] ?? '0'));
            $nb = (float) str_replace([',', '$'], '', (string)($b['Total MXN'] ?? '0'));
            return $nb <=> $na;
        });

        // Gráfica con Total MXN
        $heights = $this->scaleBars($chartVals);
        $bars = [];
        foreach ($outRows as $i => $r) {
            $bars[] = [
                'label' => mb_substr((string)$r['Cliente'], 0, 3),
                'h'     => $heights[$i] ?? 10,
            ];
        }

        $estimadoMXN = $totMXN + ($totUSD * $tipoCambio);

        return [
            'cols'  => ['Cliente', 'Órdenes', 'Monto MXN', 'Monto USD', 'Total MXN'],
            'rows'  => $outRows,
            'chart' => $bars,
            'meta'  => [
                'importe' => [
                    'mxn' => $totMXN,
                    'usd' => $totUSD,
                    'estimado_mxn' => $estimadoMXN,
                    'tipo_cambio' => $tipoCambio,
                ],
            ],
        ];
    }

    protected function spanWhere($query, $desde, $hasta, $col = 'created_at')
    {
        if ($desde) $query->where($col, '>=', $desde);
        if ($hasta) $query->where($col, '<=', $hasta);
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
}
