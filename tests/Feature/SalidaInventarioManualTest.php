<?php

use App\Models\Cliente;
use App\Models\DetalleOrdenProducto;
use App\Models\Inventario;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Ordenes\OrdenServicioService;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('descuenta inventario fisico en una salida manual sin duplicar el descuento en disponible', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteSalidaManual();
    $producto = crearProductoSalidaManual();

    Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 5,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 5,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
        'forma_ingreso' => 'recibido_almacen',
        'estado_recepcion' => 'recibido',
    ]);

    $stockService = app(OrdenServicioService::class);
    $stockService->refreshProductStockTotals($producto->codigo_producto);

    expect($stockService->calculateAvailableForProduct($producto->codigo_producto))->toBe(5);

    $response = $this
        ->actingAs($gerente)
        ->from(route('inventario.salidas'))
        ->post(route('inventario.salidas.store'), [
            'codigo_producto' => $producto->codigo_producto,
            'id_cliente' => $cliente->clave_cliente,
            'moneda' => 'MXN',
            'cantidad' => 2,
            'precio_unitario' => 150,
        ]);

    $response->assertRedirect(route('inventario.salidas'));

    $entrada = Inventario::query()->firstOrFail();
    $producto->refresh();
    $detalle = DetalleOrdenProducto::query()->firstOrFail();
    $orden = OrdenServicio::query()->firstOrFail();

    expect((int) $entrada->piezas_sueltas)->toBe(3)
        ->and((int) $producto->stock_total)->toBe(3)
        ->and($stockService->calculateAvailableForProduct($producto->codigo_producto))->toBe(3)
        ->and((int) $detalle->cantidad)->toBe(2)
        ->and($orden->tipo_orden)->toBe('salida_manual');
});

function crearClienteSalidaManual(array $attributes = []): Cliente
{
    static $seq = 1;

    $index = $seq++;

    return Cliente::query()->create(array_merge([
        'codigo_cliente' => 'CLI-SM-' . $index,
        'nombre' => 'Cliente salida manual ' . $index,
        'nombre_empresa' => 'Cliente salida manual SA de CV',
        'direccion_fiscal' => 'Av. Inventario 123',
        'contacto' => 'Contacto ' . $index,
        'telefono' => '442700' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'correo_electronico' => 'salida-manual-' . $index . '@example.com',
        'datos_fiscales' => 'RFCSM' . str_pad((string) $index, 6, '0', STR_PAD_LEFT),
        'ubicacion' => 'Queretaro',
    ], $attributes));
}

function crearProductoSalidaManual(array $attributes = []): Producto
{
    static $seq = 1;

    $index = $seq++;

    return Producto::query()->create(array_merge([
        'nombre' => 'Producto salida manual ' . $index,
        'numero_parte' => 'SM-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'pieza',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para prueba de salida manual',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ], $attributes));
}
