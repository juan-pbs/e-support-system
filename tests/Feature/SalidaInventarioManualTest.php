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

it('permite al rol sistema eliminar inventario directo sin registrar salida', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $producto = crearProductoSalidaManual([
        'numero_parte' => 'DEL-INV-001',
    ]);

    $entrada = Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 7,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 7,
        'numero_serie' => null,
        'fecha_entrada' => now()->subDays(3)->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]);

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($sistema)
        ->delete(route('inventario.eliminar', $entrada->id))
        ->assertRedirect(route('inventario'));

    expect(Inventario::query()->whereKey($entrada->id)->exists())->toBeFalse()
        ->and((int) $producto->fresh()->stock_total)->toBe(0)
        ->and(OrdenServicio::query()->where('tipo_orden', 'salida_manual')->exists())->toBeFalse();
});

it('permite al rol sistema usar seleccion multiple y eliminar inventario masivo', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $producto = crearProductoSalidaManual([
        'numero_parte' => 'BULK-INV-001',
    ]);

    $entradas = collect([4, 6])->map(fn (int $cantidad) => Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => $cantidad,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => $cantidad,
        'numero_serie' => null,
        'fecha_entrada' => now()->subDays(3)->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]));

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($sistema)
        ->get(route('inventario'))
        ->assertOk()
        ->assertSee('Vista lista compacta')
        ->assertSee('Seleccion multiple')
        ->assertSee(route('inventario.eliminar_masivo'), false);

    $this
        ->actingAs($sistema)
        ->delete(route('inventario.eliminar_masivo'), [
            'entradas' => $entradas->pluck('id')->all(),
        ])
        ->assertRedirect(route('inventario'));

    expect(Inventario::query()->whereIn('id', $entradas->pluck('id'))->exists())->toBeFalse()
        ->and((int) $producto->fresh()->stock_total)->toBe(0)
        ->and(OrdenServicio::query()->where('tipo_orden', 'salida_manual')->exists())->toBeFalse();
});

it('permite a sistema admin y gerente editar la cantidad de una salida manual sin series', function (string $rol) {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $usuario = User::factory()->create([
        'puesto' => $rol,
    ]);

    $cliente = crearClienteSalidaManual();
    $producto = crearProductoSalidaManual([
        'numero_parte' => 'EDIT-SAL-' . strtoupper($rol),
    ]);

    Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 8,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 8,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]);

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($usuario)
        ->post(route('inventario.salidas.store'), [
            'codigo_producto' => $producto->codigo_producto,
            'id_cliente' => $cliente->clave_cliente,
            'moneda' => 'MXN',
            'cantidad' => 3,
            'precio_unitario' => 150,
        ])
        ->assertRedirect(route('inventario.salidas'));

    $detalle = DetalleOrdenProducto::query()->firstOrFail();

    $this
        ->actingAs($usuario)
        ->put(route('inventario.salidas.update_cantidad', $detalle->id_orden_producto), [
            'cantidad' => 5,
        ])
        ->assertRedirect(route('inventario.salidas'));

    $detalle->refresh();
    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    expect((int) $detalle->cantidad)->toBe(5)
        ->and((float) $detalle->total)->toBe(750.0)
        ->and((int) $producto->fresh()->stock_total)->toBe(3);

    $this
        ->actingAs($usuario)
        ->put(route('inventario.salidas.update_cantidad', $detalle->id_orden_producto), [
            'cantidad' => 2,
        ])
        ->assertRedirect(route('inventario.salidas'));

    $detalle->refresh();
    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    expect((int) $detalle->cantidad)->toBe(2)
        ->and((float) $detalle->total)->toBe(300.0)
        ->and((int) $producto->fresh()->stock_total)->toBe(6);
})->with(['sistema', 'admin', 'gerente']);

it('permite al rol sistema editar libremente la cantidad de una entrada de inventario', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $producto = crearProductoSalidaManual([
        'numero_parte' => 'EDIT-ENT-SIS',
    ]);

    $entrada = Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 10,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 6,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]);

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($sistema)
        ->put(route('inventario.actualizar', $entrada->id), [
            'cantidad_ingresada' => 3,
            'costo' => 120,
            'precio' => 180,
            'fecha_caducidad' => null,
        ])
        ->assertRedirect(route('inventario'));

    $entrada->refresh();

    expect((int) $entrada->cantidad_ingresada)->toBe(3)
        ->and((int) $entrada->piezas_sueltas)->toBe(3)
        ->and((int) $producto->fresh()->stock_total)->toBe(3);
});

it('impide a roles no sistema reducir una entrada por debajo de lo ya consumido', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $producto = crearProductoSalidaManual([
        'numero_parte' => 'EDIT-ENT-GER',
    ]);

    $entrada = Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 10,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 6,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]);

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($gerente)
        ->from(route('inventario.editar', $entrada->id))
        ->put(route('inventario.actualizar', $entrada->id), [
            'cantidad_ingresada' => 3,
            'costo' => 120,
            'precio' => 180,
            'fecha_caducidad' => null,
        ])
        ->assertSessionHasErrors('cantidad_ingresada');

    $entrada->refresh();

    expect((int) $entrada->cantidad_ingresada)->toBe(10)
        ->and((int) $entrada->piezas_sueltas)->toBe(6)
        ->and((int) $producto->fresh()->stock_total)->toBe(6);
});

it('permite a roles no sistema ajustar una entrada respetando el consumo existente', function () {
    Carbon::setTestNow('2026-05-08 10:00:00');

    $admin = User::factory()->create([
        'puesto' => 'admin',
    ]);

    $producto = crearProductoSalidaManual([
        'numero_parte' => 'EDIT-ENT-ADM',
    ]);

    $entrada = Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 100,
        'precio' => 150,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 10,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 6,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
    ]);

    app(OrdenServicioService::class)->refreshProductStockTotals($producto->codigo_producto);

    $this
        ->actingAs($admin)
        ->put(route('inventario.actualizar', $entrada->id), [
            'cantidad_ingresada' => 8,
            'costo' => 120,
            'precio' => 180,
            'fecha_caducidad' => null,
        ])
        ->assertRedirect(route('inventario'));

    $entrada->refresh();

    expect((int) $entrada->cantidad_ingresada)->toBe(8)
        ->and((int) $entrada->piezas_sueltas)->toBe(4)
        ->and((int) $producto->fresh()->stock_total)->toBe(4);
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
