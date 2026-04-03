<?php

namespace App\Reports;

use App\Models\DetalleOrdenProducto;
use App\Models\OrdenServicio;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalidasInventarioReport
{
    public function build($desde = null, $hasta = null): array
    {
        if (!class_exists(DetalleOrdenProducto::class)) {
            return ['cols' => [], 'rows' => [], 'chart' => []];
        }

        $d = (new DetalleOrdenProducto())->getTable();

        $hasOrden = class_exists(OrdenServicio::class);
        $hasProd = class_exists(Producto::class);

        $o = $hasOrden ? (new OrdenServicio())->getTable() : null;
        $p = $hasProd ? (new Producto())->getTable() : null;

        $q = DB::table("$d as d")
            ->leftJoin('detalle_orden_producto_series as s', 's.id_orden_producto', '=', 'd.id_orden_producto');

        if ($hasOrden) {
            $q->leftJoin("$o as o", 'o.id_orden_servicio', '=', 'd.id_orden_servicio');
        }

        if ($hasProd) {
            $q->leftJoin("$p as p", 'p.codigo_producto', '=', 'd.codigo_producto');
        }

        $this->spanWhere($q, $desde, $hasta, 'd.created_at');

        $selectMoneda = $hasOrden
            ? DB::raw('COALESCE(o.moneda, "MXN") as moneda')
            : DB::raw('"MXN" as moneda');

        $selectNumeroParte = $hasProd
            ? 'p.numero_parte'
            : DB::raw('NULL as numero_parte');

        $driver = DB::connection()->getDriverName();
        $selectSeriesConcat = $driver === 'sqlite'
            ? DB::raw("GROUP_CONCAT(s.numero_serie, ',') as series_concat")
            : DB::raw('GROUP_CONCAT(s.numero_serie ORDER BY s.numero_serie SEPARATOR ",") as series_concat');

        $rawRows = $q->select([
            'd.id_orden_producto as id_detalle',
            $selectNumeroParte,
            'd.nombre_producto',
            'd.cantidad',
            'd.precio_unitario',
            'd.total',
            $selectMoneda,
            'd.created_at as fecha_salida',
            $selectSeriesConcat,
        ])
            ->groupBy(
                'd.id_orden_producto',
                $hasProd ? 'p.numero_parte' : DB::raw('numero_parte'),
                'd.nombre_producto',
                'd.cantidad',
                'd.precio_unitario',
                'd.total',
                'd.created_at',
                $hasOrden ? 'o.moneda' : DB::raw('moneda')
            )
            ->orderBy('d.created_at')
            ->orderBy('d.id_orden_producto')
            ->get();

        $cols = $this->uniqueCols([
            'ID detalle',
            'Fecha de salida',
            'Hora de salida',
            'Numero de parte',
            'Nombre producto',
            'Cantidad',
            'Precio unitario',
            'Total',
            'Moneda',
            'Numeros de serie',
        ]);

        $rows = $rawRows->map(function ($row) {
            $fecha = '-';
            $hora = '-';

            if ($row->fecha_salida) {
                try {
                    $date = Carbon::parse($row->fecha_salida);
                    $fecha = $date->format('d/m/Y');
                    $hora = $date->format('H:i');
                } catch (\Throwable $e) {
                    $fecha = (string) $row->fecha_salida;
                }
            }

            return [
                'ID detalle' => $row->id_detalle,
                'Fecha de salida' => $fecha,
                'Hora de salida' => $hora,
                'Numero de parte' => $row->numero_parte ?: '-',
                'Nombre producto' => $row->nombre_producto ?: '-',
                'Cantidad' => (int) ($row->cantidad ?? 0),
                'Precio unitario' => $row->precio_unitario !== null
                    ? number_format((float) $row->precio_unitario, 2, '.', '')
                    : '',
                'Total' => $row->total !== null
                    ? number_format((float) $row->total, 2, '.', '')
                    : '',
                'Moneda' => $row->moneda ?: 'MXN',
                'Numeros de serie' => filled($row->series_concat) ? (string) $row->series_concat : '-',
            ];
        })->all();

        $chart = $this->buildChart($rawRows);

        return [
            'cols' => $cols,
            'rows' => $rows,
            'chart' => $chart,
        ];
    }

    protected function buildChart($rawRows): array
    {
        $byFecha = $rawRows->groupBy(function ($row) {
            if (!$row->fecha_salida) {
                return 'Sin fecha';
            }

            try {
                return Carbon::parse($row->fecha_salida)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $row->fecha_salida;
            }
        });

        $labels = [];
        $values = [];

        foreach ($byFecha as $key => $items) {
            $values[] = (int) $items->sum('cantidad');

            if ($key === 'Sin fecha') {
                $labels[] = $key;
                continue;
            }

            try {
                $labels[] = Carbon::parse($key)->format('d/m');
            } catch (\Throwable $e) {
                $labels[] = $key;
            }
        }

        $heights = $this->scaleBars($values);

        return collect($labels)->map(function ($label, $index) use ($heights, $values) {
            return [
                'label' => $label,
                'h' => $heights[$index] ?? 10,
                'value' => $values[$index] ?? 0,
            ];
        })->all();
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

        return array_map(function ($value) use ($max) {
            $height = $max > 0 ? ($value / $max) * 90 : 10;
            return (int) max(5, min(95, round($height)));
        }, $values);
    }

    protected function uniqueCols(array $cols): array
    {
        $seen = [];
        $out = [];

        foreach ($cols as $col) {
            $key = $this->norm($col);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $col;
        }

        return $out;
    }

    protected function norm(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'Ã¡' => 'a',
            'Ã©' => 'e',
            'Ã­' => 'i',
            'Ã³' => 'o',
            'Ãº' => 'u',
            'Ã±' => 'n',
            'ÃƒÂ¡' => 'a',
            'ÃƒÂ©' => 'e',
            'ÃƒÂ­' => 'i',
            'ÃƒÂ³' => 'o',
            'ÃƒÂº' => 'u',
            'ÃƒÂ±' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
