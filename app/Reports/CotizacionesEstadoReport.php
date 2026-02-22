<?php

namespace App\Reports;

use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;

class CotizacionesEstadoReport
{
    public function build($desde = null, $hasta = null): array
    {
        if (!class_exists(Cotizacion::class)) {
            return [
                'cols'       => [],
                'rows'       => [],
                'chart'      => [],
                'meta'       => [
                    'total_processed' => 0,
                    'total_edited'    => 0,
                ],
                // para que el export no truene
                'collection' => collect(),
            ];
        }

        $table = (new Cotizacion)->getTable(); // "cotizaciones"

        // =========================================
        // 1) QUERY PRINCIPAL: DETALLE DE COTIZACIONES
        // =========================================
        $q = DB::table($table . ' as c')
            ->leftJoin('cliente as cl', 'c.registro_cliente', '=', 'cl.clave_cliente');

        // Filtro de rango: usamos COALESCE(fecha, created_at)
        $this->spanWhere($q, $desde, $hasta);

        $rawRows = $q->select(
                'c.id_cotizacion',
                'c.fecha',
                'c.vigencia',
                'c.estado_cotizacion',
                'c.moneda',
                'c.total',
                'c.process_count',
                'c.edit_count',
                'cl.nombre as cliente_nombre',
                'cl.nombre_empresa as cliente_empresa'
            )
            ->orderBy('c.fecha')
            ->orderBy('c.id_cotizacion')
            ->get();

        // Encabezados (PDF + Excel)
        $cols = [
            'Folio',
            'Cliente',
            'Empresa',
            'Fecha',
            'Vigencia',
            'Estado',
            'Veces procesada',
            'Veces editada',
            'Moneda',
            'Total',
        ];

        // Filas de la tabla para preview / PDF
        $rows = $rawRows->map(function ($r) {
            return [
                'Folio'            => $r->id_cotizacion,
                'Cliente'          => $r->cliente_nombre ?? '—',
                'Empresa'          => $r->cliente_empresa ?? '—',
                'Fecha'            => $r->fecha ?: '',
                'Vigencia'         => $r->vigencia ?: '',
                'Estado'           => $r->estado_cotizacion ?? 'borrador',
                'Veces procesada'  => (int) ($r->process_count ?? 0),
                'Veces editada'    => (int) ($r->edit_count ?? 0),
                'Moneda'           => $r->moneda ?? 'MXN',
                'Total'            => (float) ($r->total ?? 0),
            ];
        })->all();

        // =========================================
        // 2) GRÁFICO PARA PREVIEW: POR ESTADO
        // =========================================
        $byEstado = $rawRows
            ->groupBy(function ($r) {
                return $r->estado_cotizacion ?? 'borrador';
            })
            ->map->count();

        $chartValues = array_values($byEstado->all());
        $heights     = $this->scaleBars($chartValues);

        $bars = [];
        $i    = 0;
        foreach ($byEstado as $estado => $cantidad) {
            $bars[] = [
                'label'  => mb_substr($estado, 0, 3), // BOR / PRO / ...
                'h'      => $heights[$i] ?? 10,
                'value'  => (int) $cantidad,
                'estado' => $estado,
            ];
            $i++;
        }

        // =========================================
        // 3) META: COTIZACIONES PROCESADAS / EDITADAS
        // =========================================
        // total_processed = cuántas cotizaciones están en estado "Procesada"
        $totalProcessed = (int) $rawRows
            ->filter(function ($r) {
                return strtolower($r->estado_cotizacion ?? '') === 'procesada';
            })
            ->count();

        // total_edited = cuántas cotizaciones tienen al menos 1 edición
        $totalEdited = (int) $rawRows
            ->filter(function ($r) {
                return (int) ($r->edit_count ?? 0) > 0;
            })
            ->count();

        return [
            'cols'       => $cols,
            'rows'       => $rows,
            'chart'      => $bars,
            'meta'       => [
                'total_processed' => $totalProcessed,
                'total_edited'    => $totalEdited,
            ],
            // 👉 colección cruda para que el Export de Excel pueda
            // dibujar la tabla completa + gráfica con los mismos datos
            'collection' => $rawRows,
        ];
    }

    /**
     * Aplica el rango de fechas usando COALESCE(c.fecha, c.created_at)
     */
    protected function spanWhere($query, $desde, $hasta)
    {
        if (!$desde && !$hasta) {
            return $query;
        }

        if ($desde) {
            $query->whereRaw(
                "DATE(COALESCE(c.fecha, c.created_at)) >= ?",
                [$desde]
            );
        }

        if ($hasta) {
            $query->whereRaw(
                "DATE(COALESCE(c.fecha, c.created_at)) <= ?",
                [$hasta]
            );
        }

        return $query;
    }

    /**
     * Escala alturas de barras al rango 5–95 % para el preview.
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
