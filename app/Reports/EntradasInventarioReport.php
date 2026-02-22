<?php

namespace App\Reports;

use App\Models\Inventario;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EntradasInventarioReport
{
    /**
     * Construye el dataset para:
     * - Preview (tabla + gráfica)
     * - PDF
     * - Excel
     *
     * Campos:
     *  ID, Costo, Tipo de control, Cantidad,
     *  Número de serie (productos.numero_parte),
     *  Fecha de entrada, Hora de entrada, Proveedor
     */
    public function build($desde = null, $hasta = null): array
    {
        if (!class_exists(Inventario::class)) {
            return [
                'cols'  => [],
                'rows'  => [],
                'chart' => [],
            ];
        }

        $invTable = (new Inventario)->getTable(); // "inventario"

        $q = DB::table($invTable . ' as i')
            // JOIN correcto: inventario.codigo_producto => productos.codigo_producto
            ->leftJoin('productos as p', 'i.codigo_producto', '=', 'p.codigo_producto')
            // JOIN con proveedores para obtener el nombre
            ->leftJoin('proveedores as prov', 'i.clave_proveedor', '=', 'prov.clave_proveedor');

        // Rango por fecha_entrada (siempre que esa columna exista)
        $this->spanWhere($q, $desde, $hasta, 'i.fecha_entrada');

        $rawRows = $q->select(
                'i.id',
                'i.costo',
                'i.tipo_control',
                'i.cantidad_ingresada as cantidad',
                // "Número de serie" se toma de productos.numero_parte
                'p.numero_parte as numero_serie',
                'i.fecha_entrada',
                'i.hora_entrada',
                'prov.nombre as proveedor_nombre'
            )
            ->orderBy('i.fecha_entrada')
            ->orderBy('i.hora_entrada')
            ->orderBy('i.id')
            ->get();

        // ===================== COLUMNAS =====================
        $cols = [
            'ID',
            'Costo',
            'Tipo de control',
            'Cantidad',
            'Número de serie',
            'Fecha de entrada',
            'Hora de entrada',
            'Proveedor',
        ];

        // ===================== FILAS (PDF/Excel/preview) =====================
        $rows = $rawRows->map(function ($r) {
            // Fecha
            $fechaStr = '';
            if ($r->fecha_entrada) {
                try {
                    $fechaStr = Carbon::parse($r->fecha_entrada)->format('d/m/Y');
                } catch (\Exception $e) {
                    $fechaStr = (string) $r->fecha_entrada;
                }
            }

            // Hora (desde columna hora_entrada TIME)
            $horaStr = '';
            if ($r->hora_entrada) {
                try {
                    // Formato H:i (ej. 08:17)
                    $horaStr = Carbon::createFromFormat('H:i:s', $r->hora_entrada)->format('H:i');
                } catch (\Exception $e) {
                    // Si por alguna razón no matchea el formato, lo dejamos tal cual
                    $horaStr = (string) $r->hora_entrada;
                }
            }

            return [
                'ID'              => $r->id,
                'Costo'           => $r->costo !== null
                    ? number_format((float) $r->costo, 2, '.', '')
                    : '',
                'Tipo de control' => $r->tipo_control ?? '—',
                'Cantidad'        => (int) ($r->cantidad ?? 0),
                // tomado de productos.numero_parte (alias numero_serie)
                'Número de serie' => $r->numero_serie ?? '—',
                'Fecha de entrada'=> $fechaStr,
                'Hora de entrada' => $horaStr ?: '—',
                'Proveedor'       => $r->proveedor_nombre ?? '—',
            ];
        })->all();

        // ===================== GRÁFICA (cantidad por día) =====================
        $byFecha = $rawRows->groupBy(function ($r) {
            if (!$r->fecha_entrada) {
                return 'Sin fecha';
            }
            try {
                return Carbon::parse($r->fecha_entrada)->format('Y-m-d');
            } catch (\Exception $e) {
                return (string) $r->fecha_entrada;
            }
        });

        $chartValues = [];
        $labels      = [];

        foreach ($byFecha as $key => $items) {
            $totalCantidad = (int) $items->sum('cantidad');
            $chartValues[] = $totalCantidad;

            if ($key === 'Sin fecha') {
                $labels[] = 'Sin fecha';
            } else {
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

    /**
     * Aplica rango de fechas a la columna indicada
     */
    protected function spanWhere($query, $desde, $hasta, $col = 'i.fecha_entrada')
    {
        if ($desde) {
            $query->where($col, '>=', $desde);
        }
        if ($hasta) {
            $query->where($col, '<=', $hasta);
        }
        return $query;
    }

    /**
     * Escala alturas de barras al rango 5–95 % para la gráfica de preview.
     */
    protected function scaleBars(array $values): array
    {
        $max = max($values ?: [1]);

        return array_map(function ($v) use ($max) {
            $h = $max > 0 ? ($v / $max) * 90 : 10;
            return (int) max(5, min(95, round($h)));
        }, $values);
    }
}
