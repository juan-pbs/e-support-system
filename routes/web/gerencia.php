<?php

use App\Http\Controllers\Api\OrdenServicioApiController;
use App\Http\Controllers\Gerencia\Catalogo\CargaRapidaCatalogoController;
use App\Http\Controllers\Gerencia\Catalogo\CatalogoProductoController;
use App\Http\Controllers\Gerencia\Clientes\ClienteController;
use App\Http\Controllers\Gerencia\Cotizaciones\CotizacionController;
use App\Http\Controllers\Gerencia\Empleados\EmpleadoController;
use App\Http\Controllers\Gerencia\GerenteController;
use App\Http\Controllers\Gerencia\Inventario\CargaRapidaInventarioController;
use App\Http\Controllers\Gerencia\Inventario\CargaRapidaProductosController;
use App\Http\Controllers\Gerencia\Inventario\InventarioController;
use App\Http\Controllers\Gerencia\Inventario\SalidaInventarioController;
use App\Http\Controllers\Gerencia\Ordenes\OrdenServicioController;
use App\Http\Controllers\Gerencia\Ordenes\OrdenServicioPdfController;
use App\Http\Controllers\Gerencia\Proveedores\ProveedorController;
use App\Http\Controllers\Gerencia\Reportes\ReporteController;
use App\Http\Controllers\Shared\Actas\ActaConformidadController;
use App\Http\Controllers\Shared\Seguimiento\SeguimientoServiciosController;
use App\Models\Inventario;
use App\Models\OrdenServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'gerente'])->group(function () {
    Route::get('/gerente', [GerenteController::class, 'index'])
        ->name('gerente.inicio');

    Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
    Route::post('/empleados', [EmpleadoController::class, 'store'])->name('empleados.store');
    Route::put('/empleados/{id}', [EmpleadoController::class, 'update'])->name('empleados.update');
    Route::delete('/empleados/{id}', [EmpleadoController::class, 'destroy'])->name('empleados.destroy');
    Route::get('/empleados/crear', [EmpleadoController::class, 'create'])->name('empleados.crear');
    Route::get('/empleados/{id}/editar', [EmpleadoController::class, 'edit'])->name('empleados.edit');
    Route::post('/empleados/ver-password', [EmpleadoController::class, 'verPasswordAjax'])->name('empleados.verPassword');
    Route::get('/empleados/autocomplete', [EmpleadoController::class, 'autocomplete'])->name('empleados.autocomplete');

    Route::prefix('inventario')->group(function () {
        Route::get('/', [InventarioController::class, 'index'])->name('inventario');

        Route::get('/entrada', [InventarioController::class, 'entrada'])->name('entrada');
        Route::get('/entrada/autocomplete', [InventarioController::class, 'autocomplete'])->name('entrada.autocomplete');
        Route::get('/entrada/ultima-entrada/{codigo}', function ($codigo) {
            $row = Inventario::where('codigo_producto', (int) $codigo)->orderByDesc('id')->first();

            if (! $row) {
                return response()->json(['ok' => false, 'message' => 'Sin entradas para ese producto.'], 404);
            }

            return response()->json(['ok' => true, 'data' => $row]);
        })->name('entrada.ultima_entrada');
        Route::get('/entrada/{codigo_producto}', [InventarioController::class, 'entradaPorProducto'])
            ->whereNumber('codigo_producto')
            ->name('inventario.entrada');
        Route::post('/entrada', [InventarioController::class, 'registrarEntrada'])->name('entrada.store');

        Route::get('/ver/{id}', [InventarioController::class, 'show'])->name('inventario.ver');
        Route::get('/editar/{id}', [InventarioController::class, 'editar'])->name('inventario.editar');
        Route::put('/{id}/actualizar', [InventarioController::class, 'actualizar'])->name('inventario.actualizar');
        Route::delete('/{id}/eliminar', [InventarioController::class, 'eliminar'])->name('inventario.eliminar');

        Route::get('/salidas', [SalidaInventarioController::class, 'index'])->name('inventario.salidas');
        Route::post('/salidas', [SalidaInventarioController::class, 'store'])->name('inventario.salidas.store');
        Route::get('/salidas/series', [SalidaInventarioController::class, 'seriesPorProducto'])->name('inventario.salidas.series');

        Route::get('/carga-rapida', [CargaRapidaInventarioController::class, 'index'])->name('inventario.carga_rapida.index');
        Route::get('/carga-rapida/plantilla', [CargaRapidaInventarioController::class, 'plantilla'])->name('inventario.carga_rapida.plantilla');
        Route::post('/carga-rapida/preview', [CargaRapidaInventarioController::class, 'preview'])->name('inventario.carga_rapida.preview');
        Route::post('/carga-rapida/confirmar', [CargaRapidaInventarioController::class, 'confirm'])->name('inventario.carga_rapida.confirmar');

        Route::get('/carga-rapida-productos', [CargaRapidaProductosController::class, 'index'])->name('cargaRapidaProd.index');
        Route::post('/carga-rapida-productos', [CargaRapidaProductosController::class, 'procesar'])->name('cargaRapidaProd.procesar');
    });

    Route::prefix('catalogo')->group(function () {
        Route::get('/', [CatalogoProductoController::class, 'index'])->name('catalogo.index');

        Route::get('/crear', [CatalogoProductoController::class, 'crear'])->name('producto.crear');
        Route::post('/guardar', [CatalogoProductoController::class, 'guardar'])->name('producto.guardar');

        Route::get('/carga-rapida', [CargaRapidaCatalogoController::class, 'index'])->name('catalogo.carga_rapida.index');
        Route::get('/carga-rapida/plantilla', [CargaRapidaCatalogoController::class, 'plantilla'])->name('catalogo.carga_rapida.plantilla');
        Route::post('/carga-rapida/preview', [CargaRapidaCatalogoController::class, 'preview'])->name('catalogo.carga_rapida.preview');
        Route::post('/carga-rapida/confirmar', [CargaRapidaCatalogoController::class, 'confirm'])->name('catalogo.carga_rapida.confirmar');

        Route::post('/importar', [CargaRapidaCatalogoController::class, 'preview'])->name('catalogo.importar');

        Route::get('/exportar', fn () => redirect()->route('catalogo.index')->with('error', 'Exportacion no disponible.'))
            ->name('catalogo.exportar');
        Route::get('/plantilla', [CargaRapidaCatalogoController::class, 'plantilla'])->name('catalogo.plantilla');
        Route::get('/inactivos', function (Request $request) {
            $request->merge(['inactivos' => 1]);

            return app(CatalogoProductoController::class)->index($request);
        })->name('catalogo.inactivos');

        Route::put('/producto/activar/{id}', [CatalogoProductoController::class, 'activar'])->name('producto.activar');
        Route::delete('/producto/eliminar/{id}', [CatalogoProductoController::class, 'eliminar'])->name('producto.eliminar');
        Route::put('/producto/desactivar/{id}', [CatalogoProductoController::class, 'desactivar'])->name('producto.desactivar');
        Route::get('/producto/editar/{id}', [CatalogoProductoController::class, 'editar'])->name('producto.editar');
        Route::put('/producto/actualizar/{id}', [CatalogoProductoController::class, 'actualizar'])->name('producto.actualizar');
        Route::get('/autocomplete', [CatalogoProductoController::class, 'autocomplete'])->name('catalogo.autocomplete');
    });

    Route::prefix('proveedores')->group(function () {
        Route::get('/', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/nuevo', [ProveedorController::class, 'crear'])->name('proveedores.nuevo');
        Route::post('/guardar', [ProveedorController::class, 'guardar'])->name('proveedores.guardar');
        Route::get('/autocomplete', [ProveedorController::class, 'autocomplete'])->name('proveedores.autocomplete');
        Route::get('/{id}/editar', [ProveedorController::class, 'editar'])->whereNumber('id')->name('proveedores.editar');
        Route::put('/{id}', [ProveedorController::class, 'actualizar'])->whereNumber('id')->name('proveedores.actualizar');
        Route::delete('/{id}', [ProveedorController::class, 'eliminar'])->whereNumber('id')->name('proveedores.eliminar');
    });

    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('clientes');
        Route::get('/nuevo', [ClienteController::class, 'crear'])->name('clientes.nuevo');
        Route::post('/', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/editar/{id}', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/actualizar/{id}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/{id}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        Route::get('/autocomplete', [ClienteController::class, 'autocomplete'])->name('clientes.autocomplete');
        Route::get('/autocomplete-select', [ClienteController::class, 'autocompleteSelect'])->name('clientes.autocomplete.select');
        Route::put('/credito/{id}', [ClienteController::class, 'actualizarCredito'])->name('clientes.credito.actualizar');
        Route::get('/{id}/pagos', [ClienteController::class, 'mostrarPagos'])->name('clientes.pagos');
        Route::post('/{id}/pagos', [ClienteController::class, 'registrarPago'])->name('clientes.pagos.registrar');
    });

    Route::prefix('cotizaciones')->group(function () {
        Route::get('/', [CotizacionController::class, 'index'])->name('cotizaciones.vista');
        Route::get('/crear', [CotizacionController::class, 'create'])->name('cotizaciones.crear');
        Route::post('/guardar', [CotizacionController::class, 'guardar'])->name('cotizaciones.guardar');
        Route::post('/preview', [CotizacionController::class, 'preview'])->name('cotizaciones.preview');
        Route::get('/{id}/ver-pdf', [CotizacionController::class, 'verPDF'])->name('cotizaciones.verPDF');
        Route::get('/{id}/descargar-pdf', [CotizacionController::class, 'descargarPDF'])->name('cotizaciones.descargarPDF');
        Route::get('/editar/{id}', [CotizacionController::class, 'editar'])->name('cotizaciones.editar');
        Route::put('/actualizar/{id}', [CotizacionController::class, 'actualizar'])->name('cotizaciones.actualizar');
        Route::get('/autocomplete', [CotizacionController::class, 'autocomplete'])->name('cotizaciones.autocomplete');
        Route::get('/api/productos', [CotizacionController::class, 'getProducts'])->name('cotizaciones.productos');
        Route::delete('/eliminar/{id}', [CotizacionController::class, 'eliminar'])->name('cotizaciones.eliminar');
        Route::get('/procesar/{id}', [CotizacionController::class, 'procesar'])->name('cotizaciones.procesar');
    });

    Route::prefix('ordenes')->name('ordenes.')->group(function () {
        Route::get('/', [OrdenServicioController::class, 'index'])->name('index');
        Route::get('/crear', [OrdenServicioController::class, 'create'])->name('create');

        Route::get('/autocomplete', function (Request $request) {
            $term = trim((string) $request->input('term', ''));

            if ($term === '') {
                return response()->json([]);
            }

            $like = '%' . $term . '%';
            $rows = OrdenServicio::query()
                ->with('cliente')
                ->where(function ($query) use ($like) {
                    $query->where('id_orden_servicio', 'like', $like)
                        ->orWhere('servicio', 'like', $like)
                        ->orWhereHas('cliente', function ($clientQuery) use ($like) {
                            $clientQuery->where('nombre', 'like', $like)
                                ->orWhere('nombre_empresa', 'like', $like);
                        });
                })
                ->orderByDesc('id_orden_servicio')
                ->limit(10)
                ->get();

            return response()->json($rows->map(function ($orden) {
                $label = 'OS-' . $orden->id_orden_servicio;
                $cliente = trim((string) optional($orden->cliente)->nombre);

                if ($cliente !== '') {
                    $label .= ' - ' . $cliente;
                }

                if (! empty($orden->servicio)) {
                    $label .= ' (' . $orden->servicio . ')';
                }

                return ['id' => $orden->id_orden_servicio, 'label' => $label];
            })->values());
        })->name('autocomplete');

        Route::post('/preview', [OrdenServicioPdfController::class, 'previewPdf'])->name('preview');
        Route::get('/{id}/pdf', [OrdenServicioPdfController::class, 'pdf'])->name('pdf');

        Route::post('/guardar', [OrdenServicioController::class, 'store'])->name('store');
        Route::get('/{id}/editar', [OrdenServicioController::class, 'edit'])->name('edit');
        Route::get('/{id}', [OrdenServicioController::class, 'show'])->name('show');
        Route::put('/{id}', [OrdenServicioController::class, 'update'])->name('update');
        Route::delete('/{id}', [OrdenServicioController::class, 'destroy'])->name('destroy');

        Route::get('/asignar', fn () => redirect()->route('ordenes.index'))->name('asignar.index');
        Route::get('/{id}/asignar', fn ($id) => redirect()->route('ordenes.edit', ['id' => $id]))->name('asignar');
        Route::post('/{id}/asignar', fn ($id) => redirect()->route('ordenes.edit', ['id' => $id])->with('error', 'Asignacion temporalmente no disponible.'))
            ->name('asignar.guardar');
        Route::post('/{id}/seguimiento', fn ($id) => redirect()->route('ordenes.edit', ['id' => $id])->with('success', 'Seguimiento registrado.'))
            ->name('seguimiento');

        Route::get('/crear-desde-cotizacion/{id}', [OrdenServicioController::class, 'createDesdeCotizacion'])
            ->name('crearDesdeCotizacion');
        Route::post('/guardar-desde-cotizacion', [OrdenServicioController::class, 'guardarDesdeCotizacion'])
            ->name('guardarDesdeCotizacion');

        Route::get('/producto/stock', [OrdenServicioApiController::class, 'apiProductoStock'])->name('api.producto.stock');
        Route::post('/productos/store-rapido', [OrdenServicioApiController::class, 'storeRapido'])->name('productos.store-rapido');
        Route::get('/api/credito', [OrdenServicioApiController::class, 'apiCreditoCliente'])->name('api.credito');

        Route::get('/{id}/acta', [ActaConformidadController::class, 'actaVista'])->name('acta.vista');
        Route::post('/{id}/acta/draft', [ActaConformidadController::class, 'actaGuardarBorrador'])->name('acta.borrador');
        Route::post('/{id}/acta/preview', [ActaConformidadController::class, 'actaPreview'])->name('acta.preview');
        Route::post('/{id}/acta/confirm', [ActaConformidadController::class, 'actaConfirmar'])->name('acta.confirmar');
        Route::get('/{id}/acta/pdf', [ActaConformidadController::class, 'actaPdf'])->name('acta.pdf');
    });

    Route::get('/seguimiento', [SeguimientoServiciosController::class, 'index'])->name('seguimiento');

    Route::prefix('reportes')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('reportes');
        Route::get('/descargar', [ReporteController::class, 'descargar'])->name('reportes.descargar');
        Route::get('/seguimiento', [SeguimientoServiciosController::class, 'index'])->name('reportes.seguimiento');
    });

    Route::get('/api/seguimiento-servicios', [SeguimientoServiciosController::class, 'data'])
        ->name('api.seguimiento-servicios');
    Route::get('/api/ordenes/{orden}/extras', [SeguimientoServiciosController::class, 'extrasIndex'])
        ->name('api.ordenes.extras.index');
    Route::post('/api/ordenes/{orden}/extras', [SeguimientoServiciosController::class, 'extrasStore'])
        ->name('api.ordenes.extras.store');
    Route::put('/api/ordenes/{orden}/extras/{extra}', [SeguimientoServiciosController::class, 'extrasUpdate'])
        ->name('api.ordenes.extras.update');
    Route::delete('/api/ordenes/{orden}/extras/{extra}', [SeguimientoServiciosController::class, 'extrasDestroy'])
        ->name('api.ordenes.extras.destroy');
    Route::get('/api/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'seguimientosIndex'])
        ->name('api.ordenes.seguimientos.index');
    Route::post('/api/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'seguimientosStore'])
        ->name('api.ordenes.seguimientos.store');
    Route::post('/api/ordenes/{orden}/imagenes', [SeguimientoServiciosController::class, 'imagenesStore'])
        ->name('api.ordenes.imagenes.store');
    Route::post('/api/ordenes/{orden}/seguimientos/{seguimiento}/imagenes', [SeguimientoServiciosController::class, 'imagenesStore'])
        ->name('api.ordenes.seguimientos.imagenes.store');
});
