<?php

namespace App\Http\Controllers\Gerencia\Inventario;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\NumeroSerie;

class SalidaInventarioController extends Controller
{
    /**
     * Listado de salidas + datos para el modal (productos, clientes, series).
     */
    public function index(Request $request)
    {
        $buscar         = trim($request->query('buscar', ''));
        $fecha          = $request->query('fecha');
        $codigoProducto = $request->query('codigo_producto'); // ✅ viene del componente autocomplete (hidden)

        // Tablas reales
        $d  = (new DetalleOrdenProducto())->getTable(); // detalle_orden_producto
        $o  = (new OrdenServicio())->getTable();        // orden_servicio
        $p  = (new Producto())->getTable();             // productos
        $c  = (new Cliente())->getTable();              // cliente
        $ct = (new Cotizacion())->getTable();           // cotizaciones

        // Productos para el modal (con conteo de series disponibles)
        $productosLista = Producto::from("$p as pr")
            ->leftJoin('inventario as inv', 'inv.codigo_producto', '=', 'pr.codigo_producto')
            ->leftJoin('numeros_serie as ns', 'ns.inventario_id', '=', 'inv.id')
            ->groupBy('pr.codigo_producto', 'pr.nombre', 'pr.numero_parte')
            ->select([
                'pr.codigo_producto',
                'pr.nombre',
                'pr.numero_parte',
                DB::raw('COUNT(ns.id) as series_disponibles'),
            ])
            ->orderBy('pr.nombre')
            ->limit(500)
            ->get();

        // Clientes para el modal
        $clientesLista = Cliente::select('clave_cliente', 'nombre', 'nombre_empresa')
            ->orderBy('nombre')
            ->limit(500)
            ->get();

        // Listado de salidas con series
        $query = DB::table("$d as d")
            ->leftJoin('detalle_orden_producto_series as s', 's.id_orden_producto', '=', 'd.id_orden_producto')
            ->leftJoin("$o as o", 'o.id_orden_servicio', '=', 'd.id_orden_servicio')
            ->leftJoin("$p as p", 'p.codigo_producto', '=', 'd.codigo_producto')
            ->leftJoin("$c as c", 'c.clave_cliente', '=', 'o.id_cliente')
            ->leftJoin("$ct as ct", 'ct.id_cotizacion', '=', 'o.id_cotizacion')
            ->select([
                'd.id_orden_producto as id_detalle',
                'd.codigo_producto',
                'd.nombre_producto',
                DB::raw('"" as descripcion_detalle'),
                'd.cantidad',
                'd.precio_unitario',
                'd.total',
                DB::raw('COALESCE(o.moneda, "MXN") as moneda_detalle'),
                'd.created_at as fecha_salida',
                DB::raw('GROUP_CONCAT(s.numero_serie ORDER BY s.numero_serie SEPARATOR ",") as series_concat'),

                'o.id_orden_servicio',
                'o.tipo_orden',
                'o.moneda as moneda_orden',

                'c.nombre as cliente_nombre',
                'c.nombre_empresa',

                'ct.id_cotizacion',

                'p.imagen',
                'p.unidad',
                'p.numero_parte',
                'p.nombre as nombre_producto_real',
            ])
            ->when(!empty($codigoProducto), function ($qq) use ($codigoProducto) {
                // ✅ si viene del dropdown, filtra exacto por producto
                $qq->where('d.codigo_producto', $codigoProducto);
            })
            ->when(empty($codigoProducto) && $buscar !== '', function ($qq) use ($buscar) {
                // ✅ si NO eligió producto, entonces búsqueda libre
                $like = "%{$buscar}%";
                $qq->where(function ($w) use ($like) {
                    $w->where('p.nombre', 'like', $like)
                      ->orWhere('p.numero_parte', 'like', $like)
                      ->orWhere('d.nombre_producto', 'like', $like)
                      ->orWhere('c.nombre', 'like', $like)
                      ->orWhere('c.nombre_empresa', 'like', $like)
                      ->orWhere('o.id_orden_servicio', 'like', $like)
                      ->orWhere('ct.id_cotizacion', 'like', $like);
                });
            })
            ->when(!empty($fecha), function ($qq) use ($fecha) {
                $qq->whereDate('d.created_at', $fecha);
            })
            ->groupBy(
                'd.id_orden_producto',
                'd.codigo_producto',
                'd.nombre_producto',
                'd.cantidad',
                'd.precio_unitario',
                'd.total',
                'd.created_at',
                'o.id_orden_servicio',
                'o.tipo_orden',
                'o.moneda',
                'c.nombre',
                'c.nombre_empresa',
                'ct.id_cotizacion',
                'p.imagen',
                'p.unidad',
                'p.numero_parte',
                'p.nombre'
            )
            ->orderByDesc('d.created_at');

        $salidas = $query->paginate(15)->withQueryString();

        return view('gerencia.inventario.salidas', compact(
            'salidas', 'buscar', 'fecha', 'productosLista', 'clientesLista'
        ));
    }

    /**
     * Registrar salida creando una OS "salida_manual".
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_producto' => 'required|exists:productos,codigo_producto',
            'id_cliente'      => 'required|exists:cliente,clave_cliente',
            'moneda'          => 'required|in:MXN,USD',
            'tasa_cambio'     => 'nullable|numeric|min:0',
            'cantidad'        => 'nullable|numeric|min:0.0001',
            'precio_unitario' => 'nullable|numeric|min:0',
            'series'          => 'nullable|array',
            'series.*'        => 'string|max:255',
        ]);

        $seriesArr = collect($request->input('series', []))
            ->map(fn($s) => trim($s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $cantidad = $validated['cantidad'] ?? null;
        if (count($seriesArr) > 0) $cantidad = count($seriesArr);

        if ($cantidad === null) {
            return back()->with('error', 'Indica cantidad o selecciona números de serie.')->withInput();
        }

        try {
            DB::transaction(function () use ($validated, $seriesArr, $cantidad) {

                $producto = Producto::lockForUpdate()->findOrFail($validated['codigo_producto']);

                // Validar series
                if (count($seriesArr) > 0) {
                    $inventarioIds = Inventario::where('codigo_producto', $producto->codigo_producto)->pluck('id');
                    if ($inventarioIds->isEmpty()) {
                        throw new \Exception('No hay entradas de inventario para este producto.');
                    }

                    $seriesDisponibles = NumeroSerie::whereIn('inventario_id', $inventarioIds)
                        ->whereIn('numero_serie', $seriesArr)
                        ->pluck('numero_serie')
                        ->toArray();

                    if (count($seriesDisponibles) !== count($seriesArr)) {
                        throw new \Exception('Alguna serie seleccionada ya no está disponible.');
                    }
                }

                // Verificar stock
                $stockActual = (float) ($producto->stock_total ?? 0);
                $qty = (float) $cantidad;

                if ($stockActual < $qty) {
                    throw new \Exception('Stock insuficiente para realizar la salida.');
                }

                // Usuario que autoriza
                $autorizadoId = Auth::id() ?: User::orderBy('id')->value('id');
                if (!$autorizadoId) {
                    throw new \Exception('No hay usuario para "autorizado_por". Inicia sesión e inténtalo de nuevo.');
                }

                // 1) Crear OS "salida_manual"
                $os = OrdenServicio::create([
                    'id_cliente'           => $validated['id_cliente'],
                    'fecha_orden'          => now(),
                    'estado'               => 'cerrada',
                    'tipo_orden'           => 'salida_manual',
                    'servicio'             => 'Salida manual',
                    'descripcion_servicio' => 'Salida manual de inventario',
                    'moneda'               => $validated['moneda'],
                    'tasa_cambio'          => $validated['moneda'] === 'USD'
                                                ? (float)($validated['tasa_cambio'] ?? 1)
                                                : 1,
                    'precio'               => 0,
                    'costo_operativo'      => 0,
                    'impuestos'            => 0,
                    'autorizado_por'       => $autorizadoId,
                ]);

                // 2) Crear detalle
                $precioUnit = (float) ($validated['precio_unitario'] ?? 0);

                $detalle = DetalleOrdenProducto::create([
                    'id_orden_servicio' => $os->id_orden_servicio,
                    'codigo_producto'   => $producto->codigo_producto,
                    'nombre_producto'   => $producto->nombre,
                    'cantidad'          => $qty,
                    'precio_unitario'   => $precioUnit,
                    'total'             => $precioUnit * $qty,
                ]);

                // 3) Descontar stock
                $producto->stock_total = max(0, $stockActual - $qty);
                $producto->save();

                // 4) Quitar series y registrarlas
                if (count($seriesArr) > 0) {
                    $inventarioIds = Inventario::where('codigo_producto', $producto->codigo_producto)->pluck('id');

                    NumeroSerie::whereIn('inventario_id', $inventarioIds)
                        ->whereIn('numero_serie', $seriesArr)
                        ->delete();

                    $rows = array_map(function ($ns) use ($detalle) {
                        return [
                            'id_orden_producto' => $detalle->id_orden_producto,
                            'numero_serie'      => $ns,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];
                    }, $seriesArr);

                    DetalleOrdenProductoSerie::insert($rows);
                }
            });

            return redirect()->route('inventario.salidas')
                ->with('success', 'Salida manual registrada y OS generada correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'No se pudo registrar la salida.')->withInput();
        }
    }

    /**
     * Devuelve series disponibles para un producto (modal).
     */
    public function seriesPorProducto(Request $request)
    {
        $request->validate([
            'codigo_producto' => 'required|exists:productos,codigo_producto',
        ]);

        $codigo = $request->input('codigo_producto');
        $inventarioIds = Inventario::where('codigo_producto', $codigo)->pluck('id');

        $series = [];
        if ($inventarioIds->isNotEmpty()) {
            $series = NumeroSerie::whereIn('inventario_id', $inventarioIds)
                ->orderBy('numero_serie')
                ->pluck('numero_serie')
                ->toArray();
        }

        return response()->json([
            'series' => $series,
            'count'  => count($series),
        ]);
    }
}
