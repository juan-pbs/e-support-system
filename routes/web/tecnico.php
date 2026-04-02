<?php

use App\Http\Controllers\Shared\Actas\ActaConformidadController;
use App\Http\Controllers\Shared\Seguimiento\SeguimientoServiciosController;
use App\Http\Controllers\Tecnico\ServicioTecnicoController;
use App\Http\Controllers\Tecnico\TecnicoController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'seguimientosIndex'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.seguimientos.index');

        Route::post('/ordenes/{orden}/seguimientos', [SeguimientoServiciosController::class, 'seguimientosStore'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.seguimientos.store');

        Route::post('/ordenes/{orden}/seguimientos/{seguimiento}/imagenes', [SeguimientoServiciosController::class, 'imagenesStore'])
            ->whereNumber('orden')
            ->whereNumber('seguimiento')
            ->name('tecnico.api.ordenes.seguimientos.imagenes.store');

        Route::get('/ordenes/{orden}/extras', [SeguimientoServiciosController::class, 'extrasIndex'])
            ->whereNumber('orden')
            ->name('tecnico.api.ordenes.extras.index');

        Route::post('/ordenes/{orden}/extras', [SeguimientoServiciosController::class, 'extrasStore'])
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
