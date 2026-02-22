<?php

namespace App\Reports;

use App\Models\OrdenServicio;
use App\Models\DetalleOrdenProducto;
use App\Models\Producto;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class VentasReport
{
    public function build($desde = null, $hasta = null): array
    {
        $osTable   = (new OrdenServicio)->getTable();
        $detTable  = (new DetalleOrdenProducto)->getTable();
        $prodTable = (new Producto)->getTable();
        $cliTable  = (new Cliente)->getTable();

        $osCols   = Schema::getColumnListing($osTable);
        $detCols  = Schema::getColumnListing($detTable);
        $prodCols = Schema::getColumnListing($prodTable);
        $cliCols  = Schema::getColumnListing($cliTable);

        // columnas base orden_servicio
        $fechaCol     = in_array('fecha_orden', $osCols, true) ? 'fecha_orden' : 'created_at';
        $idOrdenCol   = in_array('id_orden_servicio', $osCols, true) ? 'id_orden_servicio' : 'id';
        $tipoPagoCol  = in_array('tipo_pago', $osCols, true) ? 'tipo_pago' : null;
        $monedaCol    = in_array('moneda', $osCols, true) ? 'moneda' : null;
        $estadoCol    = in_array('estado', $osCols, true) ? 'estado' : (in_array('estado_pago', $osCols, true) ? 'estado_pago' : null);
        $tipoOrdenCol = in_array('tipo_orden', $osCols, true) ? 'tipo_orden' : null;

        // columnas clave
        $anticipoCol = $this->pickCol($osCols, ['anticipo', 'monto_anticipo', 'anticipo_pagado', 'pago_inicial']);

        $costoServicioCol = $this->pickCol($osCols, ['costo_servicio', 'costo_servicio_mxn', 'costo_servicio_usd']);
        $costoOperativoCol = $this->pickCol($osCols, ['costo_operativo', 'costo_operativo_mxn', 'costo_operativo_usd', 'costo_operativo_envio']);

        $materialesNPCol = $this->pickCol($osCols, [
            'total_adicional_mxn',
            'total_adicional',
            'total_material_extra',
            'materiales_no_previstos',
            'total_materiales_no_previstos'
        ]);

        // cliente fk/pk/nombre
        $osCliFk = in_array('id_cliente', $osCols, true) ? 'id_cliente' : (in_array('cliente_id', $osCols, true) ? 'cliente_id' : null);
        $cliPk   = in_array('clave_cliente', $cliCols, true) ? 'clave_cliente' : (in_array('id', $cliCols, true) ? 'id' : null);
        $cliName = in_array('nombre', $cliCols, true) ? 'nombre' : (in_array('nombre_cliente', $cliCols, true) ? 'nombre_cliente' : $cliPk);

        // detalle fk orden/producto
        $detOsFk = in_array('id_orden_servicio', $detCols, true) ? 'id_orden_servicio' : (in_array('orden_servicio_id', $detCols, true) ? 'orden_servicio_id' : null);
        $detProdFk = collect(['id_producto', 'producto_id', 'codigo_producto', 'id_prod'])
            ->first(fn($c) => in_array($c, $detCols, true)) ?? null;

        // producto pk + numero_parte
        $prodPk = collect(['id', 'id_producto', 'codigo_producto'])
            ->first(fn($c) => in_array($c, $prodCols, true)) ?? $prodCols[0];

        $prodNumeroParteCol = collect(['numero_parte', 'sku', 'codigo', 'codigo_producto'])
            ->first(fn($c) => in_array($c, $prodCols, true));

        // Query base
        $q = DB::table("$osTable as os")
            ->leftJoin("$cliTable as c", function ($join) use ($osCliFk, $cliPk) {
                if ($osCliFk && $cliPk) $join->on("c.$cliPk", '=', "os.$osCliFk");
            });

        if ($detOsFk) {
            $q->leftJoin("$detTable as d", "d.$detOsFk", '=', "os.$idOrdenCol");
        }

        $joinProducts = $detProdFk && $prodNumeroParteCol;
        if ($joinProducts) {
            $q->leftJoin("$prodTable as p", "p.$prodPk", '=', "d.$detProdFk");
        }

        // rango fechas
        $this->spanWhere($q, $desde, $hasta, "os.$fechaCol");

        // solo finalizadas
        if ($estadoCol) {
            $q->whereRaw("LOWER(os.$estadoCol) IN ('finalizada','completada','pagada','pagado','cerrada','cerrado')");
        }

        // SELECT
        $select = [
            DB::raw("DATE(os.$fechaCol) as fecha"),
            "os.$idOrdenCol as id_orden",
            DB::raw("COALESCE(c.$cliName, '') as cliente"),
            $tipoOrdenCol ? "os.$tipoOrdenCol as tipo_orden" : DB::raw("'' as tipo_orden"),
            $tipoPagoCol  ? "os.$tipoPagoCol as tipo_pago"   : DB::raw("'' as tipo_pago"),
            $monedaCol    ? "os.$monedaCol as moneda"        : DB::raw("'MXN' as moneda"),
            $estadoCol    ? "os.$estadoCol as estado_pago"   : DB::raw("'' as estado_pago"),
        ];

        // total productos
        if ($detOsFk && in_array('cantidad', $detCols, true) && in_array('precio_unitario', $detCols, true)) {
            $select[] = DB::raw("COALESCE(SUM(d.cantidad * d.precio_unitario),0) as total_productos");
        } else {
            $sumProdCol = $this->pickCol($osCols, ['total_productos', 'total_productos_mxn', 'total_producto']);
            $select[] = $sumProdCol ? DB::raw("COALESCE(MAX(os.$sumProdCol),0) as total_productos") : DB::raw("0 as total_productos");
        }

        $select[] = $costoServicioCol  ? DB::raw("COALESCE(MAX(os.$costoServicioCol),0) as costo_servicio") : DB::raw("0 as costo_servicio");
        $select[] = $costoOperativoCol ? DB::raw("COALESCE(MAX(os.$costoOperativoCol),0) as costo_operativo") : DB::raw("0 as costo_operativo");
        $select[] = $materialesNPCol   ? DB::raw("COALESCE(MAX(os.$materialesNPCol),0) as materiales_no_previstos") : DB::raw("0 as materiales_no_previstos");
        $select[] = $anticipoCol       ? DB::raw("COALESCE(MAX(os.$anticipoCol),0) as anticipo") : DB::raw("0 as anticipo");

        // numeros_parte (solo para Excel/preview)
        if ($joinProducts) {
            $select[] = DB::raw("GROUP_CONCAT(DISTINCT p.$prodNumeroParteCol ORDER BY p.$prodNumeroParteCol SEPARATOR ', ') as numeros_parte");
        } elseif ($detProdFk) {
            $select[] = DB::raw("GROUP_CONCAT(DISTINCT d.$detProdFk ORDER BY d.$detProdFk SEPARATOR ', ') as numeros_parte");
        } else {
            $select[] = DB::raw("NULL as numeros_parte");
        }

        $rowsDb = $q->select($select)
            ->groupBy(
                DB::raw("DATE(os.$fechaCol)"),
                "os.$idOrdenCol",
                "cliente",
                "tipo_orden",
                "tipo_pago",
                "moneda",
                "estado_pago"
            )
            ->orderBy("os.$fechaCol", 'asc')
            ->orderBy("os.$idOrdenCol", 'asc')
            ->get();

        $rowsOut = [];

        $sum = [
            'productos' => ['MXN' => 0.0, 'USD' => 0.0],
            'servicios' => ['MXN' => 0.0, 'USD' => 0.0],
            'materiales_no_previstos' => ['MXN' => 0.0, 'USD' => 0.0],
            'general'   => ['MXN' => 0.0, 'USD' => 0.0],
            'anticipo'  => ['MXN' => 0.0, 'USD' => 0.0],
            'saldo'     => ['MXN' => 0.0, 'USD' => 0.0],
        ];

        foreach ($rowsDb as $row) {
            $monedaRow = strtoupper(trim((string)($row->moneda ?? 'MXN')));
            if (!in_array($monedaRow, ['MXN', 'USD'], true)) $monedaRow = 'MXN';

            $totalProductos = (float)($row->total_productos ?? 0);
            $costoServicio  = (float)($row->costo_servicio ?? 0);
            $costoOperativo = (float)($row->costo_operativo ?? 0);
            $totalServicios = $costoServicio + $costoOperativo;

            $materialesNP = (float)($row->materiales_no_previstos ?? 0);

            // Total orden = productos + servicios + materiales extra
            $totalOrden = $totalProductos + $totalServicios + $materialesNP;

            // Total pagado = anticipo
            $totalPagado = (float)($row->anticipo ?? 0);

            $saldo = max($totalOrden - $totalPagado, 0);

            $fechaCarbon = $row->fecha ? Carbon::parse($row->fecha) : null;
            $sign = $monedaRow === 'USD' ? 'US$' : '$';

            $rowsOut[] = [
                'Fecha'                 => $fechaCarbon ? $fechaCarbon->format('d/m/Y') : ((string)($row->fecha ?? '')),
                'Orden'                 => $row->id_orden,
                'Cliente'               => $row->cliente ?: '—',
                'Tipo de orden'         => (string)($row->tipo_orden ?? '—') ?: '—',
                'Tipo de pago'          => (string)($row->tipo_pago ?? '—') ?: '—',
                'Moneda'                => $monedaRow,
                'Estado'                => (string)($row->estado_pago ?? '—') ?: '—',

                'Total productos'       => $this->fmtMoneyNoSign($totalProductos),
                'Costo servicio'        => $this->fmtMoneyNoSign($costoServicio),
                'Costo operativo'       => $this->fmtMoneyNoSign($costoOperativo),
                'Total servicios'       => $this->fmtMoneyNoSign($totalServicios),

                'Materiales no previstos' => $this->fmtMoneyNoSign($materialesNP),

                // Para PDF ya te queda bonito con signo, y para Excel lo limpiamos en el controlador (abajo te dejo snippet)
                'Total orden'           => $sign . $this->fmtMoneyNoSign($totalOrden),
                'Total pagado'          => $sign . $this->fmtMoneyNoSign($totalPagado),

                'Saldo'                 => $this->fmtMoneyNoSign($saldo),
                'Números de parte'      => $row->numeros_parte ?: '—',
            ];

            $sum['productos'][$monedaRow] += $totalProductos;
            $sum['servicios'][$monedaRow] += $totalServicios;
            $sum['materiales_no_previstos'][$monedaRow] += $materialesNP;
            $sum['general'][$monedaRow]   += $totalOrden;
            $sum['anticipo'][$monedaRow]  += $totalPagado;
            $sum['saldo'][$monedaRow]     += $saldo;
        }

        $meta = [
            'num_registros' => count($rowsOut),
            'totales' => [
                'productos' => ['mxn' => $sum['productos']['MXN'], 'usd' => $sum['productos']['USD']],
                'servicios' => ['mxn' => $sum['servicios']['MXN'], 'usd' => $sum['servicios']['USD']],
                'materiales_no_previstos' => ['mxn' => $sum['materiales_no_previstos']['MXN'], 'usd' => $sum['materiales_no_previstos']['USD']],
                'general'   => ['mxn' => $sum['general']['MXN'], 'usd' => $sum['general']['USD']],
                'anticipo'  => ['mxn' => $sum['anticipo']['MXN'], 'usd' => $sum['anticipo']['USD']],
                'saldo'     => ['mxn' => $sum['saldo']['MXN'], 'usd' => $sum['saldo']['USD']],
            ],
        ];

        return [
            'cols' => [
                'Fecha',
                'Orden',
                'Cliente',
                'Tipo de orden',
                'Tipo de pago',
                'Moneda',
                'Estado',
                'Total productos',
                'Costo servicio',
                'Costo operativo',
                'Total servicios',
                'Materiales no previstos',
                'Total orden',
                'Total pagado',
                'Saldo',
                'Números de parte',
            ],
            'rows' => $rowsOut,
            'meta' => $meta,
        ];
    }

    /* ================= Helpers ================= */

    protected function pickCol(array $cols, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }

    protected function fmtMoneyNoSign(float $v): string
    {
        return number_format((float)$v, 2, '.', ',');
    }

    protected function spanWhere($query, $desde, $hasta, $col = 'created_at')
    {
        if ($desde) $query->where($col, '>=', $desde);
        if ($hasta) $query->where($col, '<=', $hasta);
        return $query;
    }
}
