<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\Ordenes\OrdenServicioService;

use App\Models\Producto;
use App\Models\CreditoCliente;

class OrdenServicioApiController extends Controller
{
    public function __construct(private OrdenServicioService $svc) {}

    public function apiBuscarProductos(Request $request)
    {
        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['items' => []]);

        $items = Producto::query()
            ->activos()
            ->where(function ($qb) use ($q) {
                $qb->where('codigo_producto', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('numero_parte', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['codigo_producto', 'nombre', 'descripcion', 'numero_parte'])
            ->map(fn($p) => [
                'codigo_producto' => $p->codigo_producto,
                'nombre'          => $p->nombre,
                'descripcion'     => $p->descripcion,
                'numero_parte'    => $p->numero_parte,
                'precio_unitario' => 0,
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function apiCrearProductoRapido(Request $request)
    {
        $data = $request->validate([
            'codigo'       => ['nullable', 'integer'],
            'nombre'       => ['required', 'string', 'max:255'],
            'descripcion'  => ['nullable', 'string'],
            'numero_parte' => ['nullable', 'string', 'max:255'],
            'unidad'       => ['nullable', 'string', 'max:50'],
        ]);

        $producto = new Producto();

        if (!empty($data['codigo'])) {
            $producto->codigo_producto = $data['codigo'];
        }

        $producto->nombre               = $data['nombre'];
        $producto->descripcion          = $data['descripcion'] ?? null;
        $producto->numero_parte         = $data['numero_parte'] ?? null;
        $producto->unidad               = $data['unidad'] ?? 'pz';
        $producto->activo               = true;
        $producto->stock_seguridad      = 0;
        $producto->stock_total          = 0;
        $producto->stock_paquetes       = 0;
        $producto->stock_piezas_sueltas = 0;
        $producto->save();

        return response()->json([
            'ok'   => true,
            'item' => [
                'codigo_producto' => $producto->codigo_producto,
                'nombre'          => $producto->nombre,
                'descripcion'     => $producto->descripcion,
                'numero_parte'    => $producto->numero_parte,
                'precio_unitario' => 0,
            ],
        ], 201);
    }

    public function storeRapido(Request $request)
    {
        $data = $request->validate([
            'nombre'       => ['required', 'string', 'max:255'],
            'descripcion'  => ['nullable', 'string'],
            'numero_parte' => ['nullable', 'string', 'max:255'],
            'unidad'       => ['nullable', 'string', 'max:50'],
            'activo'       => ['nullable', 'boolean'],
            'categoria'    => ['nullable', 'string', 'max:100'],
        ]);

        $producto = Producto::create([
            'nombre'               => $data['nombre'],
            'descripcion'          => $data['descripcion'] ?? null,
            'numero_parte'         => $data['numero_parte'] ?? null,
            'unidad'               => $data['unidad'] ?? 'pz',
            'stock_seguridad'      => 0,
            'stock_total'          => 0,
            'stock_paquetes'       => 0,
            'stock_piezas_sueltas' => 0,
            'categoria'            => $data['categoria'] ?? 'Otra',
            'activo'               => $data['activo'] ?? true,
        ]);

        return response()->json(
            $producto->only(['codigo_producto', 'nombre', 'descripcion', 'numero_parte'])
        );
    }

    public function apiAgregarLineaProducto(Request $request)
    {
        $data = $request->validate([
            'codigo_producto' => ['nullable', 'integer'],
            'nombre'          => ['nullable', 'string', 'max:255'],
            'descripcion'     => ['nullable', 'string'],
            'precio'          => ['nullable', 'numeric'],
            'cantidad'        => ['nullable', 'numeric'],
        ]);

        $cantidad = $data['cantidad'] ?? 1;
        if ($cantidad <= 0) $cantidad = 1;

        $item = [
            'codigo_producto'   => null,
            'nombre_producto'   => null,
            'descripcion'       => $data['descripcion'] ?? null,
            'cantidad'          => (float) $cantidad,
            'precio'            => (float) ($data['precio'] ?? 0),
            'ns_asignados'      => [],
            'stock_disponible'  => null,
            'stock'             => null,
            'disponible'        => null,
            'stock_max'         => null,
            'faltante'          => 0,
            'sin_stock'         => false,
            'has_serial'        => false,
        ];

        if (!empty($data['codigo_producto'])) {
            $codigo   = (int) $data['codigo_producto'];
            $producto = Producto::find($codigo);

            if ($producto) {
                $item['codigo_producto'] = $producto->codigo_producto;
                $item['nombre_producto'] = $producto->nombre;

                if (!$item['descripcion']) {
                    $item['descripcion'] = $producto->descripcion;
                }

                $stock = $this->svc->calculateAvailableForProduct($producto->codigo_producto);

                $item['stock_disponible'] = $stock;
                $item['stock']            = $stock;
                $item['disponible']       = $stock;
                $item['stock_max']        = $stock;
                $item['sin_stock']        = $stock <= 0;
                $item['has_serial']       = $this->svc->productHasSerial($producto->codigo_producto);

                $need = (int) ceil($cantidad);
                if ($need > 0 && $stock < $need) {
                    $item['faltante']  = $need - $stock;
                    $item['sin_stock'] = true;
                }
            }
        }

        if (empty($item['codigo_producto'])) {
            if (empty($data['nombre'])) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'El nombre del producto es obligatorio para líneas manuales.',
                ], 422);
            }
            $item['nombre_producto'] = $data['nombre'];
        }

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function apiProductoStock(Request $request)
    {
        $codigo = (int) $request->query('codigo', 0);
        $token  = $request->query('token'); // opcional (para excluir reservas de otros)
        if ($codigo <= 0) {
            return response()->json(['ok' => false, 'stock' => 0, 'has_serial' => false], 200);
        }

        try {
            $stock = (int) $this->svc->calculateAvailableForProduct($codigo, $token ? (string)$token : null);
            $hasSerial = (bool) $this->svc->productHasSerial($codigo);
        } catch (\Throwable $e) {
            $stock = 0;
            $hasSerial = false;
        }

        return response()->json([
            'ok' => true,
            'stock' => max($stock, 0),
            'has_serial' => $hasSerial,
        ], 200);
    }

    public function apiStock(Request $request)
    {
        return $this->apiProductoStock($request);
    }

    /**
     * ✅ FIX N/S:
     * Devuelve seriales disponibles considerando:
     * - inventario.numero_serie (1 fila por serie)
     * - fallback numeros_serie
     */
    public function apiPeekSeries(Request $request)
    {
        $codigo = (int) $request->query('codigo', 0);
        $token  = $request->query('token'); // opcional
        if ($codigo <= 0) {
            return response()->json(['ok' => true, 'series' => []], 200);
        }

        try {
            $series = $this->svc->peekSeriesAll($codigo, $token ? (string)$token : null);
        } catch (\Throwable $e) {
            $series = [];
        }

        return response()->json([
            'ok' => true,
            'series' => array_values($series),
        ], 200);
    }

    public function apiPeekSeriesCompat(Request $request)
    {
        return $this->apiPeekSeries($request);
    }

    public function apiCreditoCliente(Request $request)
    {
        $clave = (int) ($request->query('cliente') ?? $request->query('id_cliente') ?? 0);

        if ($clave <= 0) {
            return response()->json([
                'ok'             => true,
                'exists'         => false,
                'monto_maximo'   => 0,
                'monto_usado'    => 0,
                'disponible'     => 0,
                'dias_credito'   => null,
                'estatus'        => null,
                'expired'        => false,
                'fecha_limite'   => null,
                'dias_restantes' => null,
            ]);
        }

        $cred = CreditoCliente::where('clave_cliente', $clave)->first();
        if (!$cred) {
            return response()->json([
                'ok'             => true,
                'exists'         => false,
                'monto_maximo'   => 0,
                'monto_usado'    => 0,
                'disponible'     => 0,
                'dias_credito'   => null,
                'estatus'        => null,
                'expired'        => false,
                'fecha_limite'   => null,
                'dias_restantes' => null,
            ]);
        }

        $venc    = $this->svc->checkCreditoVencido($cred);
        $estatus = $venc['expired'] ? 'vencido' : ($cred->estatus ?? 'activo');

        return response()->json([
            'ok'             => true,
            'exists'         => true,
            'monto_maximo'   => (float) $cred->monto_maximo,
            'monto_usado'    => (float) $cred->monto_usado,
            'disponible'     => max((float) $cred->monto_maximo - (float) $cred->monto_usado, 0),
            'dias_credito'   => $cred->dias_credito,
            'estatus'        => $estatus,
            'expired'        => (bool) $venc['expired'],
            'fecha_limite'   => $venc['fecha_limite'],
            'dias_restantes' => $venc['dias_restantes'],
        ]);
    }

    /**
     * POST /api/inventario/reservar-series
     * Body JSON: { codigo_producto:int, token:string, series:[string] }
     */
    public function apiReservarSeries(Request $request)
    {
        $data = $request->validate([
            'codigo_producto' => ['required', 'integer', 'min:1'],
            'token'           => ['required', 'string', 'max:80'],
            'series'          => ['nullable', 'array'],
            'series.*'        => ['nullable', 'string'],
            // compat
            'seriales'        => ['nullable', 'array'],
            'seriales.*'      => ['nullable', 'string'],
        ]);

        $codigo = (int) $data['codigo_producto'];
        $token  = (string) $data['token'];

        $series = $data['series'] ?? null;
        if ($series === null) $series = $data['seriales'] ?? [];
        if (!is_array($series)) $series = [];

        try {
            $res = $this->svc->reserveSeries($codigo, $series, $token, auth()->id());
            return response()->json($res, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al reservar números de serie.',
            ], 500);
        }
    }

    /**
     * POST /api/inventario/liberar-series
     * Body JSON: { token:string, codigo_producto?:int, series?:[string] }
     */
    public function apiLiberarSeries(Request $request)
    {
        $data = $request->validate([
            'token'           => ['required', 'string', 'max:80'],
            'codigo_producto' => ['nullable', 'integer', 'min:1'],
            'series'          => ['nullable', 'array'],
            'series.*'        => ['nullable', 'string'],
            // compat
            'seriales'        => ['nullable', 'array'],
            'seriales.*'      => ['nullable', 'string'],
        ]);

        $token  = (string) $data['token'];
        $codigo = isset($data['codigo_producto']) ? (int) $data['codigo_producto'] : null;

        $series = $data['series'] ?? null;
        if ($series === null) $series = $data['seriales'] ?? null;

        try {
            $deleted = $this->svc->releaseSeries($token, is_array($series) ? $series : null, $codigo);
            return response()->json(['ok' => true, 'deleted' => (int) $deleted], 200);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'deleted' => 0], 500);
        }
    }
}
