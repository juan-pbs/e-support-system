<?php

namespace App\Reports;

use App\Models\Producto;
use App\Models\Inventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockCriticoReport
{
    /**
     * Ahora este reporte lista TODOS los productos con:
     * - Producto
     * - Stock actual
     * - Precio (última entrada de inventario)
     *
     * No usa gráfica (chart vacío).
     */
    public function build($desde = null, $hasta = null): array
    {
        $producto   = new Producto();
        $inventario = new Inventario();

        $pTable = $producto->getTable();    // 'productos'
        $iTable = $inventario->getTable();  // 'inventario'

        $pCols = Schema::getColumnListing($pTable);
        $iCols = Schema::getColumnListing($iTable);

        // Encabezados que verá la vista / PDF / Excel
        $cols = ['Producto', 'Stock actual', 'Precio (última entrada)'];

        /* =========================================================
           1) Detectar columna de STOCK en productos
           ========================================================= */
        $stockCol = null;
        foreach (['stock_total', 'stock', 'existencia'] as $candidate) {
            if (in_array($candidate, $pCols, true)) {
                $stockCol = $candidate;
                break;
            }
        }

        /* =========================================================
           2) Detectar columna de PRECIO en inventario / productos
           ========================================================= */

        // Preferimos precio de inventario (última entrada)
        $precioInvCol = null;
        foreach (['precio', 'precio_venta', 'precio_unitario'] as $candidate) {
            if (in_array($candidate, $iCols, true)) {
                $precioInvCol = $candidate;
                break;
            }
        }

        // Fallback: alguna columna de precio en productos
        $precioProdCol = null;
        foreach (['precio', 'precio_venta', 'precio_lista'] as $candidate) {
            if (in_array($candidate, $pCols, true)) {
                $precioProdCol = $candidate;
                break;
            }
        }

        /* =========================================================
           3) Query base de productos
           ========================================================= */

        $query = DB::table("$pTable as productos");

        $selects = [
            'productos.nombre as nombre',
        ];

        // Stock
        if ($stockCol) {
            $selects[] = "productos.$stockCol as stock_total";
        } else {
            $selects[] = DB::raw('NULL as stock_total');
        }

        /* =========================================================
           4) Subconsulta: precio de la ÚLTIMA entrada de inventario
           =========================================================
           Usamos:
           - inventario.codigo_producto = productos.codigo_producto
           - Orden por fecha_entrada DESC, hora_entrada DESC, id DESC
         */

        $hasCodigoProductoInventario = in_array('codigo_producto', $iCols, true);
        $hasCodigoProductoProducto   = in_array('codigo_producto', $pCols, true);

        if ($precioInvCol && $hasCodigoProductoInventario && $hasCodigoProductoProducto) {
            $selects[] = DB::raw("
                (
                    SELECT inv.$precioInvCol
                    FROM {$iTable} as inv
                    WHERE inv.codigo_producto = productos.codigo_producto
                    ORDER BY inv.fecha_entrada DESC, inv.hora_entrada DESC, inv.id DESC
                    LIMIT 1
                ) AS precio
            ");
        } elseif ($precioProdCol) {
            // Solo si no podemos usar inventario, tomamos algún precio de productos
            $selects[] = DB::raw("productos.$precioProdCol AS precio");
        } else {
            $selects[] = DB::raw('NULL AS precio');
        }

        $dbRows = $query
            ->select($selects)
            ->orderBy('productos.nombre')
            ->get();

        /* =========================================================
           5) Formateo de datos para la vista / PDF / Excel
           ========================================================= */

        $rows = [];

        foreach ($dbRows as $r) {
            $precio = $r->precio;

            $rows[] = [
                'Producto'                => $r->nombre,
                'Stock actual'            => $r->stock_total !== null ? (int) $r->stock_total : '',
                'Precio (última entrada)' => $precio !== null && $precio !== ''
                    ? number_format((float) $precio, 2, '.', ',')
                    : '',
            ];
        }

        return [
            'cols'  => $cols,
            'rows'  => $rows,
            'chart' => [], // este reporte NO usa gráfica
        ];
    }
}
