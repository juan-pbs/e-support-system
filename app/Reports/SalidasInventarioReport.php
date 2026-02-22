<?php

namespace App\Reports;

use App\Models\DetalleOrdenProducto;
use App\Models\OrdenServicio;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalidasInventarioReport
{
    public function build($desde = null, $hasta = null): array
    {
        if (!class_exists(DetalleOrdenProducto::class)) {
            return ['cols' => [], 'rows' => [], 'chart' => []];
        }

        $d = (new DetalleOrdenProducto())->getTable();

        $hasOrden = class_exists(OrdenServicio::class);
        $hasProd  = class_exists(Producto::class);

        $o = $hasOrden ? (new OrdenServicio())->getTable() : null;
        $p = $hasProd  ? (new Producto())->getTable()      : null;

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

        $rawRows = $q->select([
            'd.id_orden_producto as id_detalle',
            $selectNumeroParte,
            'd.nombre_producto',
            'd.cantidad',
            'd.precio_unitario',
            'd.total',
            $selectMoneda,
            'd.created_at as fecha_salida',
            DB::raw('GROUP_CONCAT(s.numero_serie ORDER BY s.numero_serie SEPARATOR ",") as series_concat'),
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
            ->get();

        // ✅ columnas (y luego UNIQUE por seguridad)
        $cols = [
            'ID detalle',
            'Fecha de salida',
            'Hora de salida',
            'Número de parte',
            'Nombre producto',
            'Cantidad',
            'Precio unitario',
            'Total',
            'Moneda',
            'Números de serie',
        ];

        $cols = $this->uniqueCols($cols);

        $rows = $rawRows->map(function ($r) {
            $fechaStr = '—';
            $horaStr  = '—';

            if ($r->fecha_salida) {
                try {
                    $dt       = Carbon::parse($r->fecha_salida);
                    $fechaStr = $dt->format('d/m/Y');
                    $horaStr  = $dt->format('H:i');
                } catch (\Exception $e) {
                    $fechaStr = (string) $r->fecha_salida;
                }
            }

            return [
                'ID detalle'       => $r->id_detalle,
                'Fecha de salida'  => $fechaStr,
                'Hora de salida'   => $horaStr,
                'Número de parte'  => $r->numero_parte ?? '—',
                'Nombre producto'  => $r->nombre_producto ?? '—',
                'Cantidad'         => (int) ($r->cantidad ?? 0),
                'Precio unitario'  => $r->precio_unitario !== null ? number_format((float)$r->precio_unitario, 2, '.', '') : '',
                'Total'            => $r->total !== null ? number_format((float)$r->total, 2, '.', '') : '',
                'Moneda'           => $r->moneda ?? 'MXN',
                'Números de serie' => !empty($r->series_concat) ? (string)$r->series_concat : '—',
            ];
        })->all();

        // ===== gráfica: cantidad total por día
        $byFecha = $rawRows->groupBy(function ($r) {
            if (!$r->fecha_salida) return 'Sin fecha';
            try {
                return Carbon::parse($r->fecha_salida)->format('Y-m-d');
            } catch (\Exception $e) {
                return (string) $r->fecha_salida;
            }
        });

        $chartValues = [];
        $labels      = [];

        foreach ($byFecha as $key => $items) {
            $chartValues[] = (int) $items->sum('cantidad');

            if ($key === 'Sin fecha') $labels[] = 'Sin fecha';
            else {
                try {
                    $labels[] = Carbon::parse($key)->format('d/m');
                } catch (\Exception $e) {
                    $labels[] = $key;
                }
            }
        }

        $heights = $this->scaleBars($chartValues);
        $bars    = [];

        foreach ($labels as $i => $lbl) {
            $bars[] = [
                'label' => $lbl,
                'h'     => $heights[$i] ?? 10,
                'value' => $chartValues[$i] ?? 0,
            ];
        }

        return [
            'cols'  => $cols,
            'rows'  => $rows,
            'chart' => $bars,
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

    // ✅ quita duplicadas por normalización (acentos/case)
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
