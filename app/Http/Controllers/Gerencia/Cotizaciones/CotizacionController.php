<?php

namespace App\Http\Controllers\Gerencia\Cotizaciones;

use App\Http\Controllers\Controller;

use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\CotizacionServicio;
use App\Models\DetalleCotizacionProducto;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use Carbon\Carbon;

use App\Traits\HasFirmaDigital;

// Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

class CotizacionController extends Controller
{
    use HasFirmaDigital;

    /** Carpeta privada para PDFs */
    private string $pdfDir = 'private/cotizaciones/pdfs';
    private const CONDICIONES_PAGO_DEFAULT = 'efectivo';
    private const NOTA_FIJA = 'PRECIOS SUJETOS A CAMBIO SIN PREVIO AVISO';

    private function ensurePdfDir(): void
    {
        if (!Storage::exists($this->pdfDir)) {
            Storage::makeDirectory($this->pdfDir);
        }
    }

    private function pdfStoragePath(int $id): string
    {
        return "{$this->pdfDir}/cotizacion-{$id}.pdf";
    }

    private function pdfFileName(int $id): string
    {
        return "cotizacion-{$id}.pdf";
    }

    /* ============================ LISTADO ============================ */

    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $cotizacionIdRaw = trim((string) $request->query('cotizacion_id', ''));
        $cotizacionId = (int) preg_replace('/\D+/', '', $cotizacionIdRaw);

        if ($cotizacionId <= 0 && $buscar !== '') {
            $cotizacionId = (int) preg_replace('/\D+/', '', $buscar);
        }

        $likeBuscar = "%{$buscar}%";

        $cotizaciones = Cotizacion::query()
            ->with(['cliente'])
            ->when($cotizacionId > 0, function ($q) use ($cotizacionId) {
                $q->where('id_cotizacion', $cotizacionId);
            })
            ->when($cotizacionId <= 0 && $buscar !== '', function ($q) use ($likeBuscar) {
                $q->where(function ($sub) use ($likeBuscar) {
                    $sub->orWhere('moneda', 'like', $likeBuscar)
                        ->orWhere('descripcion', 'like', $likeBuscar)
                        ->orWhereHas('cliente', function ($c) use ($likeBuscar) {
                            $c->where('nombre', 'like', $likeBuscar)
                              ->orWhere('nombre_empresa', 'like', $likeBuscar)
                              ->orWhere('correo_electronico', 'like', $likeBuscar);
                        });
                });
            })
            ->orderByDesc('id_cotizacion')
            ->paginate(12)
            ->withQueryString();

        return view('gerencia.cotizaciones.index', [
            'cotizaciones' => $cotizaciones,
            'buscar'       => $buscar,
            'cotizacion_id'=> $cotizacionIdRaw,
        ]);
    }

    /* ============================ CREATE ============================ */

    public function create()
    {
        $clientes = Cliente::select('clave_cliente','nombre','correo_electronico')
            ->orderBy('nombre')
            ->get();

        $productos = Producto::with(['inventario' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $firmaDefaultEmpresa = $this->readFirma();
        $condicionesPagoOptions = $this->condicionesPagoOptions();

        return view('gerencia.cotizaciones.create', compact(
            'clientes',
            'productos',
            'firmaDefaultEmpresa',
            'condicionesPagoOptions'
        ));
    }

    /* ============================ GUARDAR ============================ */

    public function guardar(Request $request)
    {
        $this->validateCommon($request, 'crear');

        // Guarda firma default si el usuario lo pidió (trait)
        $this->saveFirmaDefaultFromRequest($request);

        [$detalleProductos, $sumProductos] = $this->parseProductosJson($request->input('productos_json'));

        $tipo      = $request->string('tipo_solicitud')->toString();
        $servPrice = (float) $request->input('precio_servicio', 0);
        $costoOp   = (float) $request->input('costo_operativo', 0);

        $baseIva = $sumProductos + ($tipo === 'hibrido' ? $servPrice : 0);
        $iva     = round($baseIva * 0.16, 2);
        $total   = round($sumProductos + $servPrice + $costoOp + $iva, 2);

        $tasaCambioInput = $request->input('tasa_cambio', $request->input('tipo_cambio'));
        $tasaCambio = null;
        if ($tasaCambioInput !== null && $tasaCambioInput !== '') {
            $tasaCambio = (float) $tasaCambioInput;
            if ($tasaCambio <= 0) $tasaCambio = null;
        }

        // Snapshot firma (para que no cambie si cambia la default)
        $firmaSnap = $this->buildFirmaEmpresaFromRequest($request);

        $accion = $request->input('accion');
        $cotizacionId = null;

        DB::beginTransaction();
        try {
            $cotizacion = new Cotizacion();
            $cotizacion->fecha            = Carbon::now();
            $cotizacion->vigencia         = Carbon::parse($request->input('vigencia'));
            $cotizacion->moneda           = $request->string('moneda')->toString();
            $cotizacion->tipo_solicitud   = $tipo;
            $cotizacion->registro_cliente = $request->input('cliente_id');
            $cotizacion->descripcion      = $request->input('descripcion');
            $cotizacion->costo_operativo  = $costoOp;
            $cotizacion->iva              = $iva;
            $cotizacion->total            = $total;
            $cotizacion->cantidad_escrita = $this->resolveCantidadEscrita(
                $request->input('cantidad_escrita'),
                $total,
                $cotizacion->moneda
            );
            if (Schema::hasColumn('cotizaciones', 'condiciones_pago')) {
                $cotizacion->condiciones_pago = $this->resolveCondicionesPago($request->input('condiciones_pago'));
            }
            if (Schema::hasColumn('cotizaciones', 'tiempo_entrega')) {
                $cotizacion->tiempo_entrega = $this->normalizeOptionalText($request->input('tiempo_entrega'));
            }

            // Tasa cambio
            if ($tasaCambio !== null) {
                $cotizacion->tasa_cambio = $tasaCambio;
                if (Schema::hasColumn('cotizaciones','tipo_cambio')) {
                    $cotizacion->tipo_cambio = $tasaCambio;
                }
            }

            // Contadores / estado
            $cotizacion->edit_count        = 0;
            $cotizacion->last_edited_at    = null;
            $cotizacion->process_count     = 0;
            $cotizacion->last_processed_at = null;
            $cotizacion->estado_cotizacion = 'borrador';

            // Snapshot firma (BD)
            $cotizacion->firmante_nombre  = $firmaSnap['nombre'] ?? null;
            $cotizacion->firmante_puesto  = $firmaSnap['puesto'] ?? null;
            $cotizacion->firmante_empresa = $firmaSnap['empresa'] ?? null;
            $cotizacion->signature_image  = $firmaSnap['image'] ?? null;

            $cotizacion->save();
            $cotizacionId = (int) $cotizacion->id_cotizacion;

            foreach ($detalleProductos as $d) {
                $dp = new DetalleCotizacionProducto();
                $dp->id_cotizacion    = $cotizacionId;
                $dp->codigo_producto  = is_numeric($d['id']) ? (int)$d['id'] : null;
                $dp->nombre_producto  = (string) ($d['name'] ?? '');
                $dp->descripcion_item = (string) ($d['description'] ?? '');
                $dp->cantidad         = (int) ($d['quantity'] ?? 1);
                $dp->precio_unitario  = (float) ($d['price'] ?? 0);
                $dp->total            = round($dp->cantidad * $dp->precio_unitario, 2);
                $dp->unidad           = (string) ($d['unit'] ?? 'unidad');
                $dp->save();
            }

            if (in_array($tipo, ['hibrido','servicio'], true) && $servPrice > 0) {
                $serv = new CotizacionServicio();
                $serv->id_cotizacion = $cotizacionId;
                $serv->descripcion   = (string) $request->input('descripcion_servicio', '');
                $serv->precio        = $servPrice;
                $serv->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('db_error', 'Error al guardar la cotización: '.$e->getMessage());
        }

        // ✅ Generar + guardar PDF SIEMPRE (para usar archivo_pdf)
        try {
            $this->generateAndStorePdf($cotizacionId, true);
        } catch (\Throwable $e) {
            // No frenamos la creación si falla el PDF, pero dejamos aviso si quieres
            // return back()->with('error', 'Cotización guardada, pero falló la generación del PDF: '.$e->getMessage());
        }

        // Descargar inmediatamente si la vista lo pidió
        if ($accion === 'guardar_descargar') {
            return $this->descargarPDF($cotizacionId);
        }

        return redirect()
            ->route('cotizaciones.vista')
            ->with('success', 'Cotización creada correctamente.');
    }

    /* ============================ EDITAR ============================ */

    public function editar($id)
    {
        $cotizacion = Cotizacion::with(['productos','servicio','cliente'])->findOrFail($id);

        $clientes = Cliente::select('clave_cliente','nombre','correo_electronico')
            ->orderBy('nombre')
            ->get();

        $productosDisponibles = Producto::with(['inventario' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $productosJson = json_encode(
            $cotizacion->productos->map(function ($p) {
                return [
                    'id'          => $p->codigo_producto ?? ('custom-'.$p->id),
                    'name'        => $p->nombre_producto,
                    'price'       => (float) $p->precio_unitario,
                    'quantity'    => (int) $p->cantidad,
                    'unit'        => $p->unidad ?: 'unidad',
                    'image'       => '',
                    'description' => (string)($p->descripcion_item ?? ''),
                ];
            })->values(),
            JSON_UNESCAPED_UNICODE
        );

        // Default firma para el componente (no afecta al PDF si ya guardaste snapshot)
        $firmaDefaultEmpresa = $this->readFirma();
        $condicionesPagoOptions = $this->condicionesPagoOptions();

        return view('gerencia.cotizaciones.edit', [
            'cotizacion'           => $cotizacion,
            'clientes'             => $clientes,
            'productosDisponibles' => $productosDisponibles,
            'productosJson'        => $productosJson,
            'firmaDefaultEmpresa'  => $firmaDefaultEmpresa,
            'condicionesPagoOptions' => $condicionesPagoOptions,
        ]);
    }

    /* ============================ ACTUALIZAR ============================ */

    public function actualizar(Request $request, $id)
    {
        $cotizacion = Cotizacion::with(['productos','servicio'])->findOrFail($id);

        $this->validateCommon($request, 'editar');

        // Guardar firma default si el usuario lo pidió (trait)
        $this->saveFirmaDefaultFromRequest($request);

        [$detalleProductos, $sumProductos] = $this->parseProductosJson($request->input('productos_json'));

        $tipo      = $request->string('tipo_solicitud')->toString();
        $servPrice = (float) $request->input('precio_servicio', 0);
        $costoOp   = (float) $request->input('costo_operativo', 0);

        $baseIva = $sumProductos + ($tipo === 'hibrido' ? $servPrice : 0);
        $iva     = round($baseIva * 0.16, 2);
        $total   = round($sumProductos + $servPrice + $costoOp + $iva, 2);

        $tasaCambioInput = $request->input('tasa_cambio', $request->input('tipo_cambio'));
        if ($tasaCambioInput !== null && $tasaCambioInput !== '') {
            $tasaCambio = (float) $tasaCambioInput;
            if ($tasaCambio <= 0) $tasaCambio = null;
        } else {
            $tasaCambio = $cotizacion->tasa_cambio;
        }

        // Snapshot firma actualizado desde formulario (si la editas)
        $firmaSnap = $this->buildFirmaEmpresaFromRequest($request);

        DB::beginTransaction();
        try {
            $cotizacion->vigencia         = Carbon::parse($request->input('vigencia'));
            $cotizacion->moneda           = $request->string('moneda')->toString();
            $cotizacion->tipo_solicitud   = $tipo;
            $cotizacion->registro_cliente = $request->input('cliente_id');
            $cotizacion->descripcion      = $request->input('descripcion');
            $cotizacion->costo_operativo  = $costoOp;
            $cotizacion->iva              = $iva;
            $cotizacion->total            = $total;
            $cotizacion->cantidad_escrita = $this->resolveCantidadEscrita(
                $request->input('cantidad_escrita'),
                $total,
                $cotizacion->moneda
            );
            if (Schema::hasColumn('cotizaciones', 'condiciones_pago')) {
                $cotizacion->condiciones_pago = $this->resolveCondicionesPago($request->input('condiciones_pago'));
            }
            if (Schema::hasColumn('cotizaciones', 'tiempo_entrega')) {
                $cotizacion->tiempo_entrega = $this->normalizeOptionalText($request->input('tiempo_entrega'));
            }

            $cotizacion->tasa_cambio = $tasaCambio;
            if (Schema::hasColumn('cotizaciones','tipo_cambio')) {
                $cotizacion->tipo_cambio = $tasaCambio;
            }

            $cotizacion->edit_count     = (int)($cotizacion->edit_count ?? 0) + 1;
            $cotizacion->last_edited_at = Carbon::now();

            // Snapshot firma (BD)
            $cotizacion->firmante_nombre  = $firmaSnap['nombre'] ?? $cotizacion->firmante_nombre;
            $cotizacion->firmante_puesto  = $firmaSnap['puesto'] ?? $cotizacion->firmante_puesto;
            $cotizacion->firmante_empresa = $firmaSnap['empresa'] ?? $cotizacion->firmante_empresa;
            $cotizacion->signature_image  = $firmaSnap['image'] ?? $cotizacion->signature_image;

            $cotizacion->save();

            DetalleCotizacionProducto::where('id_cotizacion', $cotizacion->id_cotizacion)->delete();

            foreach ($detalleProductos as $d) {
                $dp = new DetalleCotizacionProducto();
                $dp->id_cotizacion    = $cotizacion->id_cotizacion;
                $dp->codigo_producto  = is_numeric($d['id']) ? (int)$d['id'] : null;
                $dp->nombre_producto  = (string) ($d['name'] ?? '');
                $dp->descripcion_item = (string) ($d['description'] ?? '');
                $dp->cantidad         = (int) ($d['quantity'] ?? 1);
                $dp->precio_unitario  = (float) ($d['price'] ?? 0);
                $dp->total            = round($dp->cantidad * $dp->precio_unitario, 2);
                $dp->unidad           = (string) ($d['unit'] ?? 'unidad');
                $dp->save();
            }

            if (in_array($tipo, ['hibrido','servicio'], true) && $servPrice > 0) {
                $serv = CotizacionServicio::firstOrNew(['id_cotizacion' => $cotizacion->id_cotizacion]);
                $serv->descripcion = (string) $request->input('descripcion_servicio', '');
                $serv->precio      = $servPrice;
                $serv->save();
            } else {
                CotizacionServicio::where('id_cotizacion', $cotizacion->id_cotizacion)->delete();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('db_error', 'Error al actualizar la cotización: '.$e->getMessage());
        }

        // ✅ Regenerar y reemplazar PDF al actualizar (para que NO se quede viejo)
        try {
            $this->generateAndStorePdf((int)$cotizacion->id_cotizacion, true);
        } catch (\Throwable $e) {
            // opcional: avisar
        }

        return redirect()
            ->route('cotizaciones.vista')
            ->with('success', 'Cotización actualizada correctamente.');
    }

    /* ============================ ELIMINAR ============================ */

    public function eliminar($id)
    {
        DB::beginTransaction();
        try {
            $cot = Cotizacion::findOrFail($id);

            // borrar pdf guardado si existe
            if (!empty($cot->archivo_pdf) && Storage::exists($cot->archivo_pdf)) {
                Storage::delete($cot->archivo_pdf);
            }

            DetalleCotizacionProducto::where('id_cotizacion', $cot->id_cotizacion)->delete();
            CotizacionServicio::where('id_cotizacion', $cot->id_cotizacion)->delete();
            $cot->delete();

            DB::commit();
            return back()->with('success', 'Cotización eliminada.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No fue posible eliminar: '.$e->getMessage());
        }
    }

    /* ============================ PROCESAR ============================ */

    public function procesar($id)
{
    $cot = Cotizacion::findOrFail($id);

    // 🚫 BLOQUEAR SI ESTÁ VENCIDA
    if ($cot->vigencia && \Carbon\Carbon::parse($cot->vigencia)->isPast()) {
        return back()->with(
            'error',
            'No se puede procesar la cotización porque está vencida.'
        );
    }

    DB::beginTransaction();
    try {
        $cot->process_count     = (int)($cot->process_count ?? 0) + 1;
        $cot->last_processed_at = \Carbon\Carbon::now();
        $cot->estado_cotizacion = 'procesada';
        $cot->save();

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with(
            'error',
            'No se pudo procesar la cotización: '.$e->getMessage()
        );
    }

    // ✅ REDIRECCIÓN CORRECTA
    return redirect()->route('ordenes.crearDesdeCotizacion', [
        'id' => $id
    ]);
}


    public function osCreada(Request $request, $id)
    {
        $returnTo = $request->query('return_to', route('cotizaciones.vista'));
        return redirect($returnTo)->with('success', 'Orden de Servicio generada.');
    }

    /* ============================ AUTOCOMPLETE ============================ */

    public function autocomplete(Request $request)
    {
        $termRaw = trim((string) $request->input('term', ''));
        $termNum = (int) preg_replace('/\D+/', '', $termRaw);
        $like    = "%{$termRaw}%";

        $items = Cotizacion::with('cliente')
            ->when($termRaw !== '', function ($q) use ($like, $termNum) {
                $q->where(function ($sub) use ($like, $termNum) {
                    if ($termNum > 0) $sub->orWhere('id_cotizacion', $termNum);

                    $sub->orWhere('descripcion', 'like', $like)
                        ->orWhereHas('cliente', function ($c) use ($like) {
                            $c->where('nombre', 'like', $like)
                              ->orWhere('nombre_empresa', 'like', $like)
                              ->orWhere('correo_electronico', 'like', $like);
                        });
                });
            })
            ->orderByDesc('id_cotizacion')
            ->limit(10)
            ->get()
            ->map(function ($c) {
                $nombreCliente = $c->cliente->nombre
                    ?? $c->cliente->nombre_empresa
                    ?? 'Cliente';

                return [
                    'id'    => (int) $c->id_cotizacion,
                    'label' => 'SET-' . $c->id_cotizacion . ' - ' . $nombreCliente,
                    'value' => 'SET-' . $c->id_cotizacion,
                ];
            });

        return response()->json($items);
    }

    /* ============================ API PRODUCTS ============================ */

    public function getProducts(Request $request)
    {
        $productos = Producto::with(['inventario' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function ($p) {
                return [
                    'id'          => $p->codigo_producto,
                    'nombre'      => $p->nombre,
                    'unidad'      => $p->unidad,
                    'precio'      => optional($p->inventario->first())->precio ?? 0,
                    'imagen'      => $p->imagen,
                    'categoria'   => $p->categoria,
                    'descripcion' => $p->descripcion,
                ];
            });

        return response()->json($productos);
    }

    public function exchangeRate(Request $request)
    {
        $rates = app(\App\Services\ExchangeRateService::class)->payload();

        return response()->json($rates);
    }


    /* ============================ PREVIEW (NO GUARDA) ============================ */

    public function preview(Request $request)
    {
        $request->validate([
            'moneda'         => 'required|in:MXN,USD',
            'tipo_solicitud' => 'required|in:venta,hibrido,servicio',
            'vigencia'       => 'required|date',
        ]);

        [$detalleProductos, $sumProductos] = $this->parseProductosJson($request->input('productos_json'));
        $tipo      = $request->string('tipo_solicitud')->toString();
        $servPrice = (float) $request->input('precio_servicio', 0);
        $costoOp   = (float) $request->input('costo_operativo', 0);

        $baseIva = $sumProductos + ($tipo === 'hibrido' ? $servPrice : 0);
        $iva     = round($baseIva * 0.16, 2);
        $total   = round($sumProductos + $servPrice + $costoOp + $iva, 2);

        $tasaCambioInput = $request->input('tasa_cambio', $request->input('tipo_cambio'));
        $tasaCambio = null;
        if ($tasaCambioInput !== null && $tasaCambioInput !== '') {
            $tasaCambio = (float) $tasaCambioInput;
            if ($tasaCambio <= 0) $tasaCambio = null;
        }

        $cotizacion = (object)[
            'id_cotizacion'    => 'PREVIEW',
            'fecha'            => Carbon::now(),
            'vigencia'         => Carbon::parse($request->input('vigencia')),
            'moneda'           => $request->input('moneda'),
            'descripcion'      => $request->input('descripcion'),
            'costo_operativo'  => $costoOp,
            'iva'              => $iva,
            'total'            => $total,
            'cantidad_escrita' => $this->resolveCantidadEscrita(
                $request->input('cantidad_escrita'),
                $total,
                (string) $request->input('moneda', 'MXN')
            ),
            'condiciones_pago' => $this->resolveCondicionesPago($request->input('condiciones_pago')),
            'tiempo_entrega'   => $this->normalizeOptionalText($request->input('tiempo_entrega')),
            'nota_fija'        => self::NOTA_FIJA,
            'tipo_solicitud'   => $tipo,
            'tasa_cambio'      => $tasaCambio,
        ];

        $cliente = Cliente::find($request->input('cliente_id')) ?: (object)[
            'nombre'             => 'Cliente',
            'nombre_empresa'     => '-',
            'direccion_fiscal'   => '-',
            'correo_electronico' => '-',
            'telefono'           => '-',
            'ubicacion'          => '-',
        ];

        $productos = collect($detalleProductos)->map(function ($d) {
            $total = round(((int)$d['quantity']) * ((float)$d['price']), 2);
            return (object) [
                'nombre_producto'  => $d['name'] ?? '',
                'descripcion_item' => $d['description'] ?? '',
                'cantidad'         => (int) $d['quantity'],
                'precio_unitario'  => (float) $d['price'],
                'total'            => $total,
            ];
        });

        $servicio = null;
        if (in_array($tipo, ['hibrido','servicio'], true) && $servPrice > 0) {
            $servicio = (object)[
                'descripcion' => (string) $request->input('descripcion_servicio', ''),
                'precio'      => $servPrice,
            ];
        }

        $firma = $this->buildFirmaEmpresaFromRequest($request);

        $pdfBinary = $this->renderPdfBinary($cotizacion, $cliente, $productos, $servicio, $firma, $tasaCambio);

        return response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cotizacion-preview.pdf"',
        ]);
    }

    /* ============================ VER / DESCARGAR (USA archivo_pdf) ============================ */

    public function verPDF(Request $request, $id)
    {
        $path = $this->getOrCreatePdfPath((int)$id, $request->boolean('regen'));
        $bin  = Storage::get($path);

        return response($bin, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfFileName((int)$id).'"',
        ]);
    }

    public function descargarPDF(Request $request, $id = null)
    {
        // compatibilidad con tu llamada: descargarPDF($id)
        $realId = (int) ($id ?? $request->route('id'));
        $path   = $this->getOrCreatePdfPath($realId, $request->boolean('regen'));
        $bin    = Storage::get($path);

        return response($bin, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdfFileName($realId).'"',
        ]);
    }

    /* ============================ HELPERS PDF ============================ */

    private function getOrCreatePdfPath(int $id, bool $forceRegenerate = false): string
    {
        $cot = Cotizacion::findOrFail($id);

        if (!$forceRegenerate && !empty($cot->archivo_pdf) && Storage::exists($cot->archivo_pdf)) {
            return $cot->archivo_pdf;
        }

        return $this->generateAndStorePdf($id, true);
    }

    /**
     * Genera PDF desde BD y lo guarda en storage, actualizando cotizaciones.archivo_pdf
     */
    private function generateAndStorePdf(int $id, bool $overwrite = true): string
    {
        $cotizacion = Cotizacion::with(['productos','servicio','cliente'])->findOrFail($id);

        $tasaCambio = null;
        if (!empty($cotizacion->tasa_cambio) && (float)$cotizacion->tasa_cambio > 0) {
            $tasaCambio = (float) $cotizacion->tasa_cambio;
        } elseif (!empty($cotizacion->tipo_cambio) && (float)$cotizacion->tipo_cambio > 0) {
            $tasaCambio = (float) $cotizacion->tipo_cambio;
        }

        $cliente  = $cotizacion->cliente;
        $productos = $cotizacion->productos->map(function ($p) {
            return (object)[
                'nombre_producto'  => $p->nombre_producto,
                'descripcion_item' => $p->descripcion_item,
                'cantidad'         => (int)$p->cantidad,
                'precio_unitario'  => (float)$p->precio_unitario,
                'total'            => (float)$p->total,
            ];
        });

        $servicio = $cotizacion->servicio;

        // Firma para PDF: usar snapshot si existe; si no, defaults del trait
        $firma = $this->buildFirmaEmpresaForStoredPdf($cotizacion);

        $pdfBinary = $this->renderPdfBinary($cotizacion, $cliente, $productos, $servicio, $firma, $tasaCambio);

        $this->ensurePdfDir();

        $newPath = $this->pdfStoragePath($id);

        // si había un path distinto, lo borramos
        if (!empty($cotizacion->archivo_pdf) && $cotizacion->archivo_pdf !== $newPath && Storage::exists($cotizacion->archivo_pdf)) {
            Storage::delete($cotizacion->archivo_pdf);
        }

        Storage::put($newPath, $pdfBinary);

        // guardar path en BD
        $cotizacion->archivo_pdf = $newPath;
        $cotizacion->save();

        return $newPath;
    }

    private function renderPdfBinary($cotizacion, $cliente, $productos, $servicio, array $firma, ?float $tasaCambio): string
    {
        $html = view('pdf.cotizacion', compact(
            'cotizacion',
            'cliente',
            'productos',
            'servicio',
            'firma',
            'tasaCambio'
        ))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(public_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /* ============================ VALIDACIONES + PARSE ============================ */

    private function validateCommon(Request $request, string $modo)
    {
        $rules = [
            'tipo_solicitud'       => 'required|in:venta,hibrido,servicio',
            'moneda'               => 'required|in:MXN,USD',
            'cliente_id'           => 'required',
            'vigencia'             => 'required|date',
            'costo_operativo'      => 'nullable|numeric|min:0',
            'precio_servicio'      => 'nullable|numeric|min:0',
            'descripcion'          => 'nullable|string',
            'descripcion_servicio' => 'nullable|string',
            'condiciones_pago'     => 'nullable|in:efectivo,transferencia,tarjeta,credito_cliente',
            'tiempo_entrega'       => 'nullable|string|max:255',
            'cantidad_escrita'     => 'nullable|string|max:255',
            'productos_json'       => 'nullable|string',
            'tasa_cambio'          => 'nullable|numeric|min:0',

            // firma empresa del componente (si existen en tu form)
            'firma_emp_nombre'  => 'nullable|string|max:255',
            'firma_emp_puesto'  => 'nullable|string|max:255',
            'firma_emp_empresa' => 'nullable|string|max:255',
            'firma_empresa'     => 'nullable|string',
        ];

        $request->validate($rules);

        if ($request->input('tipo_solicitud') !== 'servicio') {
            $arr = json_decode((string) $request->input('productos_json', '[]'), true);
            if (!is_array($arr) || count($arr) === 0) {
                throw ValidationException::withMessages([
                    'productos_json' => 'Debes agregar al menos un producto.',
                ]);
            }
        }
    }

    private function parseProductosJson(?string $json): array
    {
        $items = json_decode((string) $json, true);
        if (!is_array($items)) $items = [];

        $norm = [];
        $sum  = 0.0;

        foreach ($items as $it) {
            $id    = $it['id'] ?? null;
            $name  = $it['name'] ?? '';
            $qty   = (int)($it['quantity'] ?? 1);
            $price = (float)($it['price'] ?? 0);
            $unit  = $it['unit'] ?? 'unidad';
            $desc  = $it['description'] ?? '';

            $line = round($qty * $price, 2);
            $sum += $line;

            $norm[] = [
                'id'          => $id,
                'name'        => $name,
                'quantity'    => $qty,
                'price'       => $price,
                'unit'        => $unit,
                'description' => $desc,
            ];
        }

        return [$norm, round($sum, 2)];
    }

    /* ============================ FIRMA HELPERS ============================ */

    private function normalizeDataUri(?string $value): ?string
    {
        if (empty($value) || !is_string($value)) return null;

        $v = trim($value);

        if (stripos($v, 'data:image/') === 0) {
            $parts = explode('base64,', $v, 2);
            if (count($parts) === 2) {
                $v = $parts[0] . 'base64,' . str_replace(' ', '+', $parts[1]);
            }
            return $v;
        }

        return 'data:image/png;base64,' . str_replace(' ', '+', $v);
    }

    private function buildFirmaEmpresaFromRequest(Request $request): array
    {
        $defaults = $this->readFirma(); // ['nombre','puesto','empresa','image']

        $nombre  = $request->input('firma_emp_nombre', $defaults['nombre'] ?? null);
        $puesto  = $request->input('firma_emp_puesto', $defaults['puesto'] ?? null);
        $empresa = $request->input('firma_emp_empresa', $defaults['empresa'] ?? 'E-SUPPORT QUERÉTARO');

        $rawImg  = $request->input('firma_empresa', $defaults['image'] ?? null);

        return [
            'nombre'  => $nombre,
            'puesto'  => $puesto,
            'empresa' => $empresa,
            'image'   => $this->normalizeDataUri($rawImg),
        ];
    }

    private function buildFirmaEmpresaForStoredPdf(Cotizacion $cotizacion): array
    {
        // Prioridad: snapshot guardado en BD
        $snapImage = $cotizacion->signature_image ?? null;
        $snapName  = $cotizacion->firmante_nombre ?? null;
        $snapPuesto= $cotizacion->firmante_puesto ?? null;
        $snapEmp   = $cotizacion->firmante_empresa ?? null;

        if (!empty($snapImage) || !empty($snapName) || !empty($snapPuesto) || !empty($snapEmp)) {
            return [
                'nombre'  => $snapName,
                'puesto'  => $snapPuesto,
                'empresa' => $snapEmp ?: 'E-SUPPORT QUERÉTARO',
                'image'   => $this->normalizeDataUri($snapImage),
            ];
        }

        // fallback: defaults del trait
        $defaults = $this->readFirma();
        return [
            'nombre'  => $defaults['nombre'] ?? null,
            'puesto'  => $defaults['puesto'] ?? null,
            'empresa' => $defaults['empresa'] ?? 'E-SUPPORT QUERÉTARO',
            'image'   => $this->normalizeDataUri($defaults['image'] ?? null),
        ];
    }

    private function normalizeOptionalText($value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function condicionesPagoOptions(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'tarjeta' => 'Tarjeta',
            'credito_cliente' => 'Credito cliente',
        ];
    }

    private function resolveCondicionesPago($value): string
    {
        $normalized = strtolower(trim((string) $value));

        $legacyMap = [
            'credito' => 'credito_cliente',
            'crédito' => 'credito_cliente',
            'credito cliente' => 'credito_cliente',
            'crédito cliente' => 'credito_cliente',
            'contado' => 'efectivo',
        ];

        if (isset($legacyMap[$normalized])) {
            return $legacyMap[$normalized];
        }

        $options = $this->condicionesPagoOptions();

        return array_key_exists($normalized, $options)
            ? $normalized
            : self::CONDICIONES_PAGO_DEFAULT;
    }

    private function resolveCantidadEscrita($value, float $total, string $moneda): string
    {
        $text = $this->normalizeOptionalText($value);

        if ($text !== null) {
            return $text;
        }

        return $this->moneyToWordsEs($total, $moneda);
    }

    private function moneyToWordsEs(float $amount, string $currency = 'MXN'): string
    {
        $amount = round($amount, 2);
        $integer = (int) floor($amount);
        $cents = (int) round(($amount - $integer) * 100);

        if ($cents === 100) {
            $integer++;
            $cents = 0;
        }

        $currency = strtoupper(trim($currency));
        $words = $this->adjustWordsForNoun($this->numberToWordsEs($integer));
        $noun = $currency === 'USD'
            ? ($integer === 1 ? 'DOLAR' : 'DOLARES')
            : ($integer === 1 ? 'PESO' : 'PESOS');
        $suffix = $currency === 'USD' ? 'USD' : 'M.N.';

        return sprintf('%s %s %02d/100 %s', $words, $noun, $cents, $suffix);
    }

    private function adjustWordsForNoun(string $words): string
    {
        $words = preg_replace('/VEINTIUNO$/', 'VEINTIUN', $words);
        $words = preg_replace('/ Y UNO$/', ' Y UN', $words);
        $words = preg_replace('/ UNO$/', ' UN', $words);

        return $words;
    }

    private function numberToWordsEs(int $number): string
    {
        $units = [
            0 => 'CERO',
            1 => 'UNO',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
        ];

        $specials = [
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            16 => 'DIECISEIS',
            17 => 'DIECISIETE',
            18 => 'DIECIOCHO',
            19 => 'DIECINUEVE',
            20 => 'VEINTE',
            21 => 'VEINTIUNO',
            22 => 'VEINTIDOS',
            23 => 'VEINTITRES',
            24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO',
            26 => 'VEINTISEIS',
            27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO',
            29 => 'VEINTINUEVE',
        ];

        $tens = [
            3 => 'TREINTA',
            4 => 'CUARENTA',
            5 => 'CINCUENTA',
            6 => 'SESENTA',
            7 => 'SETENTA',
            8 => 'OCHENTA',
            9 => 'NOVENTA',
        ];

        $hundreds = [
            1 => 'CIENTO',
            2 => 'DOSCIENTOS',
            3 => 'TRESCIENTOS',
            4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS',
            6 => 'SEISCIENTOS',
            7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS',
            9 => 'NOVECIENTOS',
        ];

        if ($number < 10) {
            return $units[$number];
        }

        if ($number < 30) {
            return $specials[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $rest = $number % 10;

            return $tens[$ten] . ($rest > 0 ? ' Y ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number === 100) {
            return 'CIEN';
        }

        if ($number < 1000) {
            $hundred = intdiv($number, 100);
            $rest = $number % 100;

            return $hundreds[$hundred] . ($rest > 0 ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number < 2000) {
            return 'MIL' . ($number % 1000 > 0 ? ' ' . $this->numberToWordsEs($number % 1000) : '');
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;

            return $this->numberToWordsEs($thousands)
                . ' MIL'
                . ($rest > 0 ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        if ($number < 2000000) {
            return 'UN MILLON' . ($number % 1000000 > 0 ? ' ' . $this->numberToWordsEs($number % 1000000) : '');
        }

        if ($number < 1000000000000) {
            $millions = intdiv($number, 1000000);
            $rest = $number % 1000000;

            return $this->adjustWordsForNoun($this->numberToWordsEs($millions))
                . ' MILLONES'
                . ($rest > 0 ? ' ' . $this->numberToWordsEs($rest) : '');
        }

        return (string) $number;
    }
}
