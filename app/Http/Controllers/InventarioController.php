<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Ordenes\OrdenServicioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class InventarioController extends Controller
{
    public function __construct(private OrdenServicioService $ordenService) {}

    public function index(Request $request)
    {
        $q = Inventario::query()
            ->with([
                'producto:codigo_producto,nombre,numero_parte',
                'proveedor:clave_proveedor,nombre,rfc'
            ])
            ->orderByDesc('created_at');

        if ($request->filled('codigo_producto')) {
            $q->where('codigo_producto', (int) $request->codigo_producto);
        } elseif ($request->filled('buscar')) {
            $t = '%' . trim((string)$request->buscar) . '%';

            $q->where(function ($qq) use ($t) {
                $qq->whereHas('producto', function ($p) use ($t) {
                    $p->where('nombre', 'like', $t)
                        ->orWhere('numero_parte', 'like', $t);
                })
                    ->orWhereHas('proveedor', function ($p) use ($t) {
                        $p->where('nombre', 'like', $t)
                            ->orWhere('rfc', 'like', $t);
                    });
            });
        }

        if ($request->filled('tipo_control')) {
            $q->where('tipo_control', $request->tipo_control);
        }

        $entradas = $q->paginate(20)->withQueryString();

        return view('vistas-gerente.inventario-gerente.inventario_gerente', compact('entradas'));
    }

    public function entrada(Request $request)
    {
        if ($request->filled('codigo_producto')) {
            return $this->entradaPorProducto($request->codigo_producto);
        }

        $proveedores = Proveedor::orderBy('nombre')->get(['clave_proveedor', 'nombre', 'rfc']);
        return view('vistas-gerente.inventario-gerente.entrada_inventario_gerente', compact('proveedores'));
    }

    public function entradaPorProducto($codigo_producto)
    {
        $producto = Producto::findOrFail($codigo_producto);
        $proveedores = Proveedor::orderBy('nombre')->get(['clave_proveedor', 'nombre', 'rfc']);

        $stock = [
            'total'    => (int) ($producto->stock_total ?? 0),
            'paquetes' => (int) ($producto->stock_paquetes ?? 0),
            'sueltas'  => (int) ($producto->stock_piezas_sueltas ?? 0),
        ];

        // ✅ ÚLTIMO TIPO DE CONTROL REAL (BD)
        $ultimoTipoControl = Inventario::where('codigo_producto', $producto->codigo_producto)
            ->orderByDesc('id')
            ->value('tipo_control') ?: 'PIEZAS';

        // 1) numeros_serie (si lo usas)
        $series = DB::table('numeros_serie')
            ->join('inventario', 'inventario.id', '=', 'numeros_serie.inventario_id')
            ->where('inventario.codigo_producto', $producto->codigo_producto)
            ->orderBy('numeros_serie.numero_serie')
            ->pluck('numeros_serie.numero_serie');

        // 2) fallback a inventario.numero_serie (tu caso actual)
        if ($series->isEmpty()) {
            $series = Inventario::where('codigo_producto', $producto->codigo_producto)
                ->whereNotNull('numero_serie')
                ->orderBy('numero_serie')
                ->pluck('numero_serie');
        }

        return view(
            'vistas-gerente.inventario-gerente.entrada_inventario_gerente',
            compact('producto', 'proveedores', 'stock', 'series', 'ultimoTipoControl')
        );
    }

    public function autocomplete(Request $request)
    {
        $term = '%' . $request->get('term', '') . '%';

        $res = Producto::where('nombre', 'like', $term)
            ->orWhere('numero_parte', 'like', $term)
            ->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'label' => $p->nombre . ($p->numero_parte ? ' (' . $p->numero_parte . ')' : ''),
                    'id'    => $p->codigo_producto,
                ];
            });

        return response()->json($res);
    }

    public function registrarEntrada(Request $request)
    {
        $request->validate([
            'codigo_producto' => ['required', 'exists:productos,codigo_producto'],
            'clave_proveedor' => ['nullable', 'exists:proveedores,clave_proveedor'],
            'costo'           => ['required', 'numeric', 'min:0'],
            'precio'          => ['nullable', 'numeric', 'min:0'],
            'tipo_control'    => ['required', Rule::in(['PIEZAS', 'PAQUETES', 'SERIE'])],

            'cantidad_ingresada' => ['required_if:tipo_control,PIEZAS,PAQUETES', 'integer', 'min:1'],
            'piezas_por_paquete' => ['required_if:tipo_control,PAQUETES', 'integer', 'min:1'],
            'numeros_serie'      => ['required_if:tipo_control,SERIE', 'string'],

            'fecha_caducidad'    => ['nullable', 'date'],
        ], [
            'cantidad_ingresada.required_if' => 'La cantidad es obligatoria.',
            'cantidad_ingresada.min'         => 'La cantidad debe ser mayor a cero.',
            'piezas_por_paquete.required_if' => 'Piezas por paquete es obligatorio.',
            'numeros_serie.required_if'      => 'Debes capturar al menos un número de serie.',
        ]);

        $codigoProducto = (int) $request->codigo_producto;

        $tieneSeries = Inventario::where('codigo_producto', $codigoProducto)
            ->whereNotNull('numero_serie')
            ->exists();

        if ($tieneSeries && $request->tipo_control !== 'SERIE') {
            return back()->withInput()->with('error', 'Este producto ya maneja números de serie. Solo puedes registrar entradas tipo SERIE.');
        }

        $fechaEntrada = Carbon::now()->toDateString();
        $horaEntrada  = Carbon::now()->format('H:i:s');

        DB::transaction(function () use ($request, $fechaEntrada, $horaEntrada, $codigoProducto) {
            $tipo = $request->tipo_control;

            $base = [
                'codigo_producto' => $codigoProducto,
                'clave_proveedor' => $request->filled('clave_proveedor') ? $request->clave_proveedor : null,
                'costo'           => (float) $request->costo,
                'precio'          => $request->filled('precio') ? (float) $request->precio : 0,
                'tipo_control'    => $tipo,
                'fecha_entrada'   => $fechaEntrada,
                'hora_entrada'    => $horaEntrada,
                'fecha_caducidad' => $request->fecha_caducidad ?: null,
            ];

            if ($tipo === 'SERIE') {
                $series = $this->parseSeries($request->numeros_serie);

                if ($series->isEmpty()) {
                    throw ValidationException::withMessages(['numeros_serie' => 'Debes capturar al menos un número de serie.']);
                }

                $duplicadas = DB::table('inventario')
                    ->whereIn('numero_serie', $series->all())
                    ->pluck('numero_serie')
                    ->unique()
                    ->values();

                if ($duplicadas->count()) {
                    throw ValidationException::withMessages([
                        'numeros_serie' => 'Estas series ya existen: ' . $duplicadas->take(20)->implode(', ')
                    ]);
                }

                foreach ($series as $serie) {
                    Inventario::create(array_merge($base, [
                        'cantidad_ingresada' => 1,
                        'piezas_por_paquete' => null,
                        'paquetes_restantes' => 0,
                        'piezas_sueltas'     => 1,
                        'numero_serie'       => $serie,
                    ]));
                }
            } elseif ($tipo === 'PAQUETES') {
                $paquetes = (int) $request->cantidad_ingresada;
                $ppp      = (int) $request->piezas_por_paquete;

                Inventario::create(array_merge($base, [
                    'cantidad_ingresada' => $paquetes,
                    'piezas_por_paquete' => $ppp,
                    'paquetes_restantes' => $paquetes,
                    'piezas_sueltas'     => 0,
                    'numero_serie'       => null,
                ]));
            } else { // PIEZAS
                $piezas = (int) $request->cantidad_ingresada;

                Inventario::create(array_merge($base, [
                    'cantidad_ingresada' => $piezas,
                    'piezas_por_paquete' => null,
                    'paquetes_restantes' => 0,
                    'piezas_sueltas'     => $piezas,
                    'numero_serie'       => null,
                ]));
            }

            $this->recalcularStockProducto($codigoProducto);
        });

        return redirect()->route('inventario')->with('success', 'Entrada registrada.');
    }

    public function show($id)
    {
        $entrada = Inventario::with(['producto', 'proveedor'])->findOrFail($id);

        $seriesEntrada = collect();

        if (($entrada->tipo_control ?? '') === 'SERIE') {
            // 1) si existe numeros_serie para este inventario_id
            $seriesEntrada = DB::table('numeros_serie')
                ->where('inventario_id', $entrada->id)
                ->orderBy('numero_serie')
                ->pluck('numero_serie');

            // 2) fallback: tu esquema actual (1 fila por serie en inventario)
            if ($seriesEntrada->isEmpty()) {
                $loteIds = $this->loteSerieQuery($entrada)->pluck('id');
                $seriesEntrada = Inventario::whereIn('id', $loteIds)
                    ->whereNotNull('numero_serie')
                    ->orderBy('numero_serie')
                    ->pluck('numero_serie');
            }
        }

        return view('vistas-gerente.inventario-gerente.editar_inventario_gerente', compact('entrada', 'seriesEntrada'));
    }

    public function editar($id)
    {
        return $this->show($id);
    }

    public function actualizar(Request $request, $id)
    {
        $entrada = Inventario::with(['producto', 'proveedor'])->findOrFail($id);

        $rules = [
            'costo'           => ['required', 'numeric', 'min:0'],
            'precio'          => ['nullable', 'numeric', 'min:0'],
            'fecha_caducidad' => ['nullable', 'date', 'after_or_equal:' . $entrada->fecha_entrada],
        ];

        if (($entrada->tipo_control ?? '') === 'SERIE') {
            $rules['numeros_serie'] = ['required', 'string'];
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $entrada) {

            $nuevoCosto  = (float) $request->costo;
            $nuevoPrecio = $request->filled('precio') ? (float) $request->precio : 0;
            $nuevaCad    = $request->fecha_caducidad ?: null;

            if (($entrada->tipo_control ?? '') !== 'SERIE') {
                $entrada->costo = $nuevoCosto;
                $entrada->precio = $nuevoPrecio;
                $entrada->fecha_caducidad = $nuevaCad;
                $entrada->save();
                return;
            }

            // ===== SERIE editable =====
            $seriesNuevas = $this->parseSeries($request->numeros_serie);

            if ($seriesNuevas->isEmpty()) {
                throw ValidationException::withMessages(['numeros_serie' => 'Debes capturar al menos un número de serie.']);
            }

            $usaTabla = DB::table('numeros_serie')->where('inventario_id', $entrada->id)->exists();

            if ($usaTabla) {
                $dups = DB::table('numeros_serie')
                    ->whereIn('numero_serie', $seriesNuevas->all())
                    ->where('inventario_id', '<>', $entrada->id)
                    ->pluck('numero_serie')
                    ->unique();

                if ($dups->count()) {
                    throw ValidationException::withMessages([
                        'numeros_serie' => 'Estas series ya existen: ' . $dups->take(20)->implode(', ')
                    ]);
                }

                DB::table('numeros_serie')->where('inventario_id', $entrada->id)->delete();
                foreach ($seriesNuevas as $s) {
                    DB::table('numeros_serie')->insert([
                        'inventario_id' => $entrada->id,
                        'numero_serie'  => $s,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                $entrada->costo = $nuevoCosto;
                $entrada->precio = $nuevoPrecio;
                $entrada->fecha_caducidad = $nuevaCad;
                $entrada->save();

                return;
            }

            // ===== 1 fila por serie en inventario =====
            $loteQuery = $this->loteSerieQuery($entrada);
            $lote = $loteQuery->get(['id', 'numero_serie']);

            $idsLote = $lote->pluck('id')->values();

            $dups = Inventario::whereIn('numero_serie', $seriesNuevas->all())
                ->whereNotIn('id', $idsLote->all())
                ->pluck('numero_serie')
                ->unique()
                ->values();

            if ($dups->count()) {
                throw ValidationException::withMessages([
                    'numeros_serie' => 'Estas series ya existen: ' . $dups->take(20)->implode(', ')
                ]);
            }

            $keeperSerie = $seriesNuevas->first();

            Inventario::whereIn('id', $idsLote->all())->update([
                'costo' => $nuevoCosto,
                'precio' => $nuevoPrecio,
                'fecha_caducidad' => $nuevaCad,
            ]);

            Inventario::where('id', $entrada->id)->update([
                'numero_serie' => $keeperSerie,
            ]);

            $restoDeseado = $seriesNuevas->slice(1)->values();

            $otros = $lote->filter(fn($r) => (int)$r->id !== (int)$entrada->id)->values();
            $seriesActualesOtros = $otros->pluck('numero_serie')->filter()->values();

            $idsDuplicadosKeeper = $otros->filter(fn($r) => $r->numero_serie === $keeperSerie)->pluck('id');
            if ($idsDuplicadosKeeper->count()) {
                Inventario::whereIn('id', $idsDuplicadosKeeper->all())->delete();
                $otros = $otros->filter(fn($r) => $r->numero_serie !== $keeperSerie)->values();
                $seriesActualesOtros = $otros->pluck('numero_serie')->filter()->values();
            }

            $aBorrar = $seriesActualesOtros->diff($restoDeseado)->values();
            if ($aBorrar->count()) {
                $idsBorrar = $otros->filter(fn($r) => $aBorrar->contains($r->numero_serie))->pluck('id');
                Inventario::whereIn('id', $idsBorrar->all())->delete();
            }

            $faltantes = $restoDeseado->diff($seriesActualesOtros)->values();
            if ($faltantes->count()) {
                $base = [
                    'codigo_producto' => $entrada->codigo_producto,
                    'clave_proveedor' => $entrada->clave_proveedor,
                    'costo'           => $nuevoCosto,
                    'precio'          => $nuevoPrecio,
                    'tipo_control'    => 'SERIE',
                    'fecha_entrada'   => $entrada->fecha_entrada,
                    'hora_entrada'    => $entrada->hora_entrada,
                    'fecha_caducidad' => $nuevaCad,
                ];

                foreach ($faltantes as $serie) {
                    Inventario::create(array_merge($base, [
                        'cantidad_ingresada' => 1,
                        'piezas_por_paquete' => null,
                        'paquetes_restantes' => 0,
                        'piezas_sueltas'     => 1,
                        'numero_serie'       => $serie,
                    ]));
                }
            }

            $this->recalcularStockProducto($entrada->codigo_producto);
        });

        return redirect()->route('inventario')->with('success', 'Entrada actualizada.');
    }
    public function ultimaEntrada($codigo)
    {
        $codigoProducto = (int) $codigo;

        $row = Inventario::where('codigo_producto', $codigoProducto)
            ->orderByDesc('id')
            ->first(['id', 'codigo_producto', 'tipo_control', 'costo', 'precio', 'fecha_entrada', 'hora_entrada', 'fecha_caducidad']);

        if (!$row) {
            return response()->json([
                'ok' => false,
                'message' => 'Sin entradas para ese producto.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $row,
        ]);
    }
    public function eliminar($id)
    {
        $e = Inventario::findOrFail($id);
        $producto = $e->codigo_producto;
        $e->delete();

        $this->recalcularStockProducto($producto);

        return redirect()->route('inventario')->with('success', 'Entrada eliminada.');
    }

    private function recalcularStockProducto($codigoProducto)
    {
        $this->ordenService->refreshProductStockTotals((int) $codigoProducto);
    }

    private function parseSeries(string $raw)
    {
        return collect(preg_split('/\r\n|\r|\n/', (string)$raw))
            ->map(fn($s) => trim($s))
            ->filter()
            ->unique()
            ->values();
    }

    private function loteSerieQuery(Inventario $entrada)
    {
        $q = Inventario::query()
            ->where('codigo_producto', $entrada->codigo_producto)
            ->where('tipo_control', 'SERIE')
            ->where('fecha_entrada', $entrada->fecha_entrada)
            ->where('hora_entrada', $entrada->hora_entrada);

        if (is_null($entrada->clave_proveedor)) {
            $q->whereNull('clave_proveedor');
        } else {
            $q->where('clave_proveedor', $entrada->clave_proveedor);
        }

        return $q;
    }
}

