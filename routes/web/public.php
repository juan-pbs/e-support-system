<?php

use App\Http\Controllers\Api\OrdenServicioApiController;
use App\Http\Controllers\Gerencia\Cotizaciones\CotizacionController;
use App\Http\Controllers\Shared\Actas\ActaConformidadController;
use App\Http\Controllers\Shared\LoginController;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/redireccion', [LoginController::class, 'index'])->name('dashboard');

Route::get('/api/producto/{codigo}/ultima-entrada', function ($codigo) {
    return Inventario::where('codigo_producto', $codigo)
        ->latest('created_at')
        ->first(['costo', 'precio', 'tipo_control']);
});

Route::get('/api/producto/{id}', fn ($id) => Producto::findOrFail($id));

Route::prefix('api')->group(function () {
    Route::get('/productos/autocomplete', [ActaConformidadController::class, 'autocomplete'])
        ->name('productos.autocomplete');

    Route::get('/productos/buscar', [OrdenServicioApiController::class, 'apiBuscarProductos'])
        ->name('api.productos.buscar');

    Route::post('/productos/crear-rapido', [OrdenServicioApiController::class, 'apiCrearProductoRapido'])
        ->middleware(['auth', 'gerente'])
        ->name('api.productos.crear_rapido');

    Route::get('/tipo-cambio', [CotizacionController::class, 'exchangeRate'])
        ->name('api.tipo-cambio');
});

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/inventario/peek-series', [OrdenServicioApiController::class, 'apiPeekSeries'])
        ->name('inventario.peekSeries');

    Route::post('/inventario/reservar-series', [OrdenServicioApiController::class, 'apiReservarSeries'])
        ->name('inventario.series.reserve');

    Route::post('/inventario/liberar-series', [OrdenServicioApiController::class, 'apiLiberarSeries'])
        ->name('inventario.series.release');
});
