<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controladores
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\TecnicoController;
use App\Http\Controllers\GerenteController;

use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\CatalogoProductoController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioTecnicoController;
use App\Http\Controllers\SalidaInventarioController;
use App\Http\Controllers\SeguimientoServiciosController;
use App\Http\Controllers\OrdenMaterialExtraController;
use App\Http\Controllers\CargaRapidaProductosController;
use App\Http\Controllers\ActaConformidadController;
use App\Http\Controllers\CargaRapidaCatalogoController;
use App\Http\Controllers\CargaRapidaInventarioController;

// ✅ nuevos
use App\Http\Controllers\OrdenServicioPdfController;
use App\Http\Controllers\OrdenServicioApiController;

/*
|--------------------------------------------------------------------------
| Modelos usados en closures
|--------------------------------------------------------------------------
*/

use App\Models\Inventario;

/*
|--------------------------------------------------------------------------
| Rutas públicas / sin auth
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'));

// Redirección después del login
Route::get('/redireccion', [LoginController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| APIs utilitarias rápidas (sin grupo especial)
|--------------------------------------------------------------------------
*/

// Última entrada de inventario por código de producto
Route::get('/api/producto/{codigo}/ultima-entrada', function ($codigo) {
    return Inventario::where('codigo_producto', $codigo)
        ->latest('created_at')
        ->first(['costo', 'precio', 'tipo_control']);
});

// Obtener producto por ID
Route::get('/api/producto/{id}', fn($id) => \App\Models\Producto::findOrFail($id));

/*
|--------------------------------------------------------------------------
| Endpoints API globales (públicos / de uso general)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {

    // Autocomplete de productos (se queda igual)
    Route::get('/productos/autocomplete', [ActaConformidadController::class, 'autocomplete'])
        ->name('productos.autocomplete');

    // ✅ Búsqueda de productos
    Route::get('/productos/buscar', [OrdenServicioApiController::class, 'apiBuscarProductos'])
        ->name('api.productos.buscar');

    // ✅ Alta rápida de producto
    Route::post('/productos/crear-rapido', [OrdenServicioApiController::class, 'apiCrearProductoRapido'])
        ->name('api.productos.crear_rapido');

    // Tipo de cambio (CotizacionController)
    Route::get('/tipo-cambio', [CotizacionController::class, 'exchangeRate'])
        ->name('api.tipo-cambio');
});

/*
|--------------------------------------------------------------------------
| ✅ Endpoints API protegidos (requieren sesión)
| - Peek series
| - Reservar / liberar series (nuevo)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('api')->group(function () {

    // ✅ Peek de números de serie (ahora protegido con auth)
    Route::get('/inventario/peek-series', [OrdenServicioApiController::class, 'apiPeekSeries'])
        ->name('inventario.peekSeries');

    // ✅ NUEVO: reservar números de serie (bloquea disponibilidad)
    Route::post('/inventario/reservar-series', [OrdenServicioApiController::class, 'apiReservarSeries'])
        ->name('inventario.series.reserve');

    // ✅ NUEVO: liberar números de serie (regresa disponibilidad)
    Route::post('/inventario/liberar-series', [OrdenServicioApiController::class, 'apiLiberarSeries'])
        ->name('inventario.series.release');
});

/*
|--------------------------------------------------------------------------
| Perfil de usuario (auth genérico)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Rutas base por rol: Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->middleware('admin');
});

/*
|--------------------------------------------------------------------------
| MÓDULO TÉCNICO
|--------------------------------------------------------------------------
*/

Route::prefix('tecnico')->middleware(['auth', 'tecnico'])->group(function () {

    Route::get('/', [ServicioTecnicoController::class, 'dashboard'])
        ->name('tecnico.inicio');

    Route::get('/servicios/autocomplete', [ServicioTecnicoController::class, 'autocomplete'])
        ->name('tecnico.servicios.autocomplete');

    Route::get('/historial', [TecnicoController::class, 'historial'])
        ->name('tecnico.historial');

    Route::get('/contratos', [TecnicoController::class, 'contratos'])
        ->name('tecnico.contratos');

    Route::get('/servicios', [ServicioTecnicoController::class, 'index'])
        ->name('tecnico.servicios');

    Route::get('/detalles/{orden?}', [ServicioTecnicoController::class, 'detalles'])
        ->name('tecnico.detalles');

    Route::get('/proyecto', [ServicioTecnicoController::class, 'proyecto'])
        ->name('tecnico.proyecto');

    Route::get('/detalles-proyecto/{orden}', [ServicioTecnicoController::class, 'detallesProyecto'])
        ->whereNumber('orden')
        ->name('tecnico.detalles_proyecto');

    Route::get('/acta', [ServicioTecnicoController::class, 'acta'])
        ->name('tecnico.acta');

    Route::prefix('api')->group(function () {

        Route::get('/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'progress'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.seguimientos.index');

        Route::post('/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'storeComment'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.seguimientos.store');

        Route::post('/ordenes/{orden}/seguimientos/{seguimiento}/imagenes', [SeguimientoServiciosController::class, 'storeImages'])
            ->whereNumber('orden')
            ->whereNumber('seguimiento')
            ->name('tecnico.api.ordenes.seguimientos.imagenes.store');

        Route::get('/ordenes/{orden}/extras', [OrdenMaterialExtraController::class, 'index'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.extras.index');

        Route::post('/ordenes/{orden}/extras', [OrdenMaterialExtraController::class, 'store'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.extras.store');
    });

    Route::prefix('ordenes')->name('tecnico.ordenes.')->group(function () {

        Route::post('/{orden}/seguimientos', [ServicioTecnicoController::class, 'storeSeguimiento'])
            ->whereNumber('orden')
            ->name('seguimientos.store');

        Route::post('/{orden}/extras', [ServicioTecnicoController::class, 'storeExtra'])
            ->whereNumber('orden')
            ->name('extras.store');

        Route::put('/{orden}/seguimientos/{seguimiento}', [ServicioTecnicoController::class, 'updateSeguimiento'])
            ->whereNumber('orden')
            ->whereNumber('seguimiento')
            ->name('seguimientos.update');

        Route::delete('/{orden}/seguimientos/{seguimiento}', [ServicioTecnicoController::class, 'destroySeguimiento'])
            ->whereNumber('orden')
            ->whereNumber('seguimiento')
            ->name('seguimientos.destroy');

        Route::delete('/{orden}/imagenes/{imagen}', [ServicioTecnicoController::class, 'destroyImagen'])
            ->whereNumber('orden')
            ->whereNumber('imagen')
            ->name('imagenes.destroy');

        Route::put('/{orden}/extras/{extra}', [ServicioTecnicoController::class, 'updateExtra'])
            ->whereNumber('orden')
            ->whereNumber('extra')
            ->name('extras.update');

        Route::delete('/{orden}/extras/{extra}', [ServicioTecnicoController::class, 'destroyExtra'])
            ->whereNumber('orden')
            ->whereNumber('extra')
            ->name('extras.destroy');

        Route::get('/{id}/acta', [ActaConformidadController::class, 'actaVista'])
            ->whereNumber('id')
            ->name('acta.vista');

        Route::post('/{id}/acta/draft', [ActaConformidadController::class, 'actaGuardarBorrador'])
            ->whereNumber('id')
            ->name('acta.borrador');

        Route::post('/{id}/acta/preview', [ActaConformidadController::class, 'actaPreview'])
            ->whereNumber('id')
            ->name('acta.preview');

        Route::post('/{id}/acta/confirm', [ActaConformidadController::class, 'actaConfirmar'])
            ->whereNumber('id')
            ->name('acta.confirmar');

        Route::get('/{id}/acta/pdf', [ActaConformidadController::class, 'actaPdf'])
            ->whereNumber('id')
            ->name('acta.pdf');
    });
});

/*
|--------------------------------------------------------------------------
| MÓDULO GERENTE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'gerente'])->group(function () {

    Route::get('/gerente', [GerenteController::class, 'index'])
        ->name('gerente.inicio');

    /* ======================= EMPLEADOS ======================= */

    Route::get('/empleados',             [EmpleadoController::class, 'index'])->name('empleados.index');
    Route::post('/empleados',            [EmpleadoController::class, 'store'])->name('empleados.store');
    Route::put('/empleados/{id}',        [EmpleadoController::class, 'update'])->name('empleados.update');
    Route::delete('/empleados/{id}',     [EmpleadoController::class, 'destroy'])->name('empleados.destroy');
    Route::get('/empleados/crear',       [EmpleadoController::class, 'create'])->name('empleados.crear');
    Route::get('/empleados/{id}/editar', [EmpleadoController::class, 'edit'])->name('empleados.edit');
    Route::post('/empleados/ver-password', [EmpleadoController::class, 'verPasswordAjax'])->name('empleados.verPassword');
    Route::get('/empleados/autocomplete', [EmpleadoController::class, 'autocomplete'])->name('empleados.autocomplete');

    /* ======================= INVENTARIO ======================= */
    Route::prefix('inventario')->group(function () {
        Route::get('/', [InventarioController::class, 'index'])->name('inventario');

        Route::get('/entrada', [InventarioController::class, 'entrada'])->name('entrada');
        Route::get('/entrada/autocomplete', [InventarioController::class, 'autocomplete'])->name('entrada.autocomplete');
        Route::get('/entrada/ultima-entrada/{codigo}', [InventarioController::class, 'ultimaEntrada'])->name('entrada.ultima_entrada');
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

        // Nueva carga rápida exclusiva de inventario
        Route::get('/carga-rapida', [CargaRapidaInventarioController::class, 'index'])->name('inventario.carga_rapida.index');
        Route::post('/carga-rapida/preview', [CargaRapidaInventarioController::class, 'preview'])->name('inventario.carga_rapida.preview');
        Route::post('/carga-rapida/confirmar', [CargaRapidaInventarioController::class, 'confirm'])->name('inventario.carga_rapida.confirmar');

        // Compatibilidad con la carga híbrida anterior
        Route::get('/carga-rapida-productos', [CargaRapidaProductosController::class, 'index'])->name('cargaRapidaProd.index');
        Route::post('/carga-rapida-productos', [CargaRapidaProductosController::class, 'procesar'])->name('cargaRapidaProd.procesar');
    });

    /* ======================= CATÁLOGO ======================== */
    Route::prefix('catalogo')->group(function () {
        Route::get('/', [CatalogoProductoController::class, 'index'])->name('catalogo.index');

        Route::get('/crear', [CatalogoProductoController::class, 'crear'])->name('producto.crear');
        Route::post('/guardar', [CatalogoProductoController::class, 'guardar'])->name('producto.guardar');

        // Nueva carga rápida exclusiva de catálogo
        Route::get('/carga-rapida', [CargaRapidaCatalogoController::class, 'index'])->name('catalogo.carga_rapida.index');
        Route::post('/carga-rapida/preview', [CargaRapidaCatalogoController::class, 'preview'])->name('catalogo.carga_rapida.preview');
        Route::post('/carga-rapida/confirmar', [CargaRapidaCatalogoController::class, 'confirm'])->name('catalogo.carga_rapida.confirmar');

        // Alias para no romper formularios viejos que hacían POST a /catalogo/importar
        Route::post('/importar', [CargaRapidaCatalogoController::class, 'preview'])->name('catalogo.importar');

        Route::get('/exportar', [CatalogoProductoController::class, 'exportar'])->name('catalogo.exportar');
        Route::get('/plantilla', [CatalogoProductoController::class, 'plantilla'])->name('catalogo.plantilla');
        Route::get('/inactivos', [CatalogoProductoController::class, 'inactivos'])->name('catalogo.inactivos');

        Route::put('/producto/activar/{id}', [CatalogoProductoController::class, 'activar'])->name('producto.activar');
        Route::delete('/producto/eliminar/{id}', [CatalogoProductoController::class, 'eliminar'])->name('producto.eliminar');
        Route::put('/producto/desactivar/{id}', [CatalogoProductoController::class, 'desactivar'])->name('producto.desactivar');
        Route::get('/producto/editar/{id}', [CatalogoProductoController::class, 'editar'])->name('producto.editar');
        Route::put('/producto/actualizar/{id}', [CatalogoProductoController::class, 'actualizar'])->name('producto.actualizar');
        Route::get('/autocomplete', [CatalogoProductoController::class, 'autocomplete'])->name('catalogo.autocomplete');
    });

    /* ======================= PROVEEDORES ===================== */

    Route::prefix('proveedores')->group(function () {
        Route::get('/',          [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/nuevo',     [ProveedorController::class, 'crear'])->name('proveedores.nuevo');
        Route::post('/guardar',  [ProveedorController::class, 'guardar'])->name('proveedores.guardar');
        Route::get('/autocomplete', [ProveedorController::class, 'autocomplete'])->name('proveedores.autocomplete');
        Route::get('/{id}/editar', [ProveedorController::class, 'editar'])->whereNumber('id')->name('proveedores.editar');
        Route::put('/{id}',        [ProveedorController::class, 'actualizar'])->whereNumber('id')->name('proveedores.actualizar');
        Route::delete('/{id}',     [ProveedorController::class, 'eliminar'])->whereNumber('id')->name('proveedores.eliminar');
    });

    /* ======================= CLIENTES ======================== */

    Route::prefix('clientes')->group(function () {
        Route::get('/',          [ClienteController::class, 'index'])->name('clientes');
        Route::get('/nuevo',     [ClienteController::class, 'crear'])->name('clientes.nuevo');

        Route::post('/',         [ClienteController::class, 'store'])->name('clientes.store');

        Route::get('/editar/{id}',     [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/actualizar/{id}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/{id}',         [ClienteController::class, 'destroy'])->name('clientes.destroy');

        Route::get('/autocomplete', [ClienteController::class, 'autocomplete'])->name('clientes.autocomplete');
        Route::get('/autocomplete-select', [ClienteController::class, 'autocompleteSelect'])->name('clientes.autocomplete.select');

        Route::put('/credito/{id}', [ClienteController::class, 'actualizarCredito'])->name('clientes.credito.actualizar');

        Route::get('/{id}/pagos',  [ClienteController::class, 'mostrarPagos'])->name('clientes.pagos');
        Route::post('/{id}/pagos', [ClienteController::class, 'registrarPago'])->name('clientes.pagos.registrar');
    });

    /* ======================= COTIZACIONES ==================== */

    Route::prefix('cotizaciones')->group(function () {

        Route::get('/',                [CotizacionController::class, 'index'])->name('cotizaciones.vista');
        Route::get('/crear',           [CotizacionController::class, 'create'])->name('cotizaciones.crear');
        Route::post('/guardar',        [CotizacionController::class, 'guardar'])->name('cotizaciones.guardar');

        Route::post('/preview',        [CotizacionController::class, 'preview'])->name('cotizaciones.preview');

        Route::get('/{id}/ver-pdf',        [CotizacionController::class, 'verPDF'])->name('cotizaciones.verPDF');
        Route::get('/{id}/descargar-pdf',  [CotizacionController::class, 'descargarPDF'])->name('cotizaciones.descargarPDF');

        Route::get('/editar/{id}',     [CotizacionController::class, 'editar'])->name('cotizaciones.editar');
        Route::put('/actualizar/{id}', [CotizacionController::class, 'actualizar'])->name('cotizaciones.actualizar');

        Route::get('/autocomplete',    [CotizacionController::class, 'autocomplete'])->name('cotizaciones.autocomplete');

        Route::get('/api/productos',   [CotizacionController::class, 'getProducts'])->name('cotizaciones.productos');

        Route::delete('/eliminar/{id}', [CotizacionController::class, 'eliminar'])->name('cotizaciones.eliminar');

        Route::get('/procesar/{id}',   [CotizacionController::class, 'procesar'])->name('cotizaciones.procesar');
    });

    /* ======================= ÓRDENES DE SERVICIO ============= */

    Route::prefix('ordenes')->name('ordenes.')->group(function () {

        Route::get('/',      [OrdenServicioController::class, 'index'])->name('index');
        Route::get('/crear', [OrdenServicioController::class, 'create'])->name('create');

        Route::get('/autocomplete', [OrdenServicioController::class, 'autocomplete'])->name('autocomplete');

        // ✅ preview/pdf ahora en controlador PDF
        Route::post('/preview', [OrdenServicioPdfController::class, 'previewPdf'])->name('preview');
        Route::get('/{id}/pdf', [OrdenServicioPdfController::class, 'pdf'])->name('pdf');

        Route::post('/guardar', [OrdenServicioController::class, 'store'])->name('store');
        Route::get('/{id}/editar', [OrdenServicioController::class, 'edit'])->name('edit');

        Route::get('/{id}',    [OrdenServicioController::class, 'show'])->name('show');
        Route::put('/{id}',    [OrdenServicioController::class, 'update'])->name('update');
        Route::delete('/{id}', [OrdenServicioController::class, 'destroy'])->name('destroy');

        Route::get('/asignar',        [OrdenServicioController::class, 'asignar'])->name('asignar.index');
        Route::get('/{id}/asignar',   [OrdenServicioController::class, 'asignarVista'])->name('asignar');
        Route::post('/{id}/asignar',  [OrdenServicioController::class, 'guardarAsignacion'])->name('asignar.guardar');

        Route::post('/{id}/seguimiento', [OrdenServicioController::class, 'agregarSeguimiento'])->name('seguimiento');

        Route::get('/crear-desde-cotizacion/{id}', [OrdenServicioController::class, 'crearDesdeCotizacion'])
            ->name('crearDesdeCotizacion');

        Route::post('/guardar-desde-cotizacion',   [OrdenServicioController::class, 'guardarDesdeCotizacion'])
            ->name('guardarDesdeCotizacion');

        // ✅ APIs internas de ordenes
        Route::get('/producto/stock',          [OrdenServicioApiController::class, 'apiProductoStock'])->name('api.producto.stock');
        Route::post('/productos/store-rapido', [OrdenServicioApiController::class, 'storeRapido'])->name('productos.store-rapido');
        Route::get('/api/credito',             [OrdenServicioApiController::class, 'apiCreditoCliente'])->name('api.credito');

        /*
        |--------------------------------------------------------------------------
        | ACTA DE CONFORMIDAD (SOLO GERENTE)
        |--------------------------------------------------------------------------
        */
        Route::get('/{id}/acta',          [ActaConformidadController::class, 'actaVista'])->name('acta.vista');
        Route::post('/{id}/acta/draft',   [ActaConformidadController::class, 'actaGuardarBorrador'])->name('acta.borrador');
        Route::post('/{id}/acta/preview', [ActaConformidadController::class, 'actaPreview'])->name('acta.preview');
        Route::post('/{id}/acta/confirm', [ActaConformidadController::class, 'actaConfirmar'])->name('acta.confirmar');
        Route::get('/{id}/acta/pdf',      [ActaConformidadController::class, 'actaPdf'])->name('acta.pdf');
    });

    /* ======================= SEGUIMIENTO Y REPORTES ========== */

    Route::get('/seguimiento', [SeguimientoServiciosController::class, 'index'])->name('seguimiento');

    Route::prefix('reportes')->group(function () {
        Route::get('/',           [ReporteController::class, 'index'])->name('reportes');
        Route::get('/descargar',  [ReporteController::class, 'descargar'])->name('reportes.descargar');
        Route::get('/seguimiento', [SeguimientoServiciosController::class, 'index'])->name('reportes.seguimiento');
    });

    Route::get('/api/seguimiento-servicios', [SeguimientoServiciosController::class, 'data'])
        ->name('api.seguimiento-servicios');

    Route::get('/api/ordenes/{orden}/extras',  [OrdenMaterialExtraController::class, 'index'])
        ->name('api.ordenes.extras.index');

    Route::post('/api/ordenes/{orden}/extras', [OrdenMaterialExtraController::class, 'store'])
        ->name('api.ordenes.extras.store');

    Route::put('/api/ordenes/{orden}/extras/{extra}', [OrdenMaterialExtraController::class, 'update'])
        ->name('api.ordenes.extras.update');

    Route::delete('/api/ordenes/{orden}/extras/{extra}', [OrdenMaterialExtraController::class, 'destroy'])
        ->name('api.ordenes.extras.destroy');

    Route::get('/api/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'progress'])
        ->name('api.ordenes.seguimientos.index');

    Route::post('/api/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'storeComment'])
        ->name('api.ordenes.seguimientos.store');

    Route::post('/api/ordenes/{orden}/imagenes', [SeguimientoServiciosController::class, 'storeImages'])
        ->name('api.ordenes.imagenes.store');

    Route::post('/api/ordenes/{orden}/seguimientos/{seguimiento}/imagenes', [SeguimientoServiciosController::class, 'storeImages'])
        ->name('api.ordenes.seguimientos.imagenes.store');
});

/*
|--------------------------------------------------------------------------
| Auth scaffolding
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
