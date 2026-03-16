<?php

use App\Models\Cliente;
use App\Models\CreditoCliente;
use App\Models\DetalleOrdenProducto;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('guarda el credito usando la fecha limite como fuente de dias restantes', function () {
    Carbon::setTestNow('2026-03-15 10:00:00');

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearCliente();
    $fechaLimite = Carbon::today()->addDays(30)->toDateString();

    $response = $this
        ->actingAs($gerente)
        ->from(route('clientes'))
        ->put(route('clientes.credito.actualizar', $cliente->clave_cliente), [
            'monto_maximo' => 5000,
            'fecha_asignacion' => $fechaLimite,
        ]);

    $response->assertRedirect(route('clientes'));

    $this->assertDatabaseHas('credito_cliente', [
        'clave_cliente' => $cliente->clave_cliente,
        'monto_maximo' => 5000,
        'dias_credito' => 30,
        'fecha_asignacion' => $fechaLimite,
        'estatus' => 'activo',
    ]);
});

it('marca vencido el credito cuando la fecha limite es hoy en la api de ordenes', function () {
    Carbon::setTestNow('2026-03-15 08:00:00');

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearCliente();

    CreditoCliente::create([
        'clave_cliente' => $cliente->clave_cliente,
        'monto_maximo' => 2500,
        'monto_usado' => 300,
        'dias_credito' => 0,
        'fecha_asignacion' => Carbon::today()->toDateString(),
        'estatus' => 'activo',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->getJson(route('ordenes.api.credito', [
            'cliente' => $cliente->clave_cliente,
        ]));

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'exists' => true,
            'estatus' => 'vencido',
            'expired' => true,
            'fecha_limite' => Carbon::today()->toDateString(),
            'dias_restantes' => 0,
        ]);
});

it('incluye materiales en el total final y el saldo pendiente de la orden', function () {
    $cliente = crearCliente();

    $producto = Producto::create([
        'nombre' => 'Bomba de prueba',
        'numero_parte' => 'NP-001',
        'unidad' => 'pieza',
        'categoria' => 'Refacciones',
        'descripcion' => 'Producto para pruebas',
        'stock_seguridad' => 0,
        'stock_total' => 10,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 10,
        'activo' => true,
    ]);

    $orden = OrdenServicio::create([
        'id_cliente' => $cliente->clave_cliente,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'En proceso',
        'precio' => 200,
        'costo_operativo' => 20,
        'impuestos' => 32,
        'total_adicional_mxn' => 10,
        'anticipo_mxn' => 50,
        'moneda' => 'MXN',
        'tipo_pago' => 'efectivo',
        'tipo_orden' => 'servicio_simple',
    ]);

    DetalleOrdenProducto::create([
        'id_orden_servicio' => $orden->id_orden_servicio,
        'codigo_producto' => $producto->codigo_producto,
        'nombre_producto' => $producto->nombre,
        'cantidad' => 2,
        'precio_unitario' => 50,
        'total' => 100,
    ]);

    $orden = $orden->fresh();

    expect($orden->materiales_total)->toBe(100.0)
        ->and($orden->total_final)->toBe(362.0)
        ->and($orden->saldo_pendiente)->toBe(312.0);
});

it('genera el precio escrito en la orden cuando no se captura manualmente', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearCliente();

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('ordenes.store'), [
            'id_cliente' => $cliente->clave_cliente,
            'servicio' => 'Instalacion de equipo',
            'tipo_orden' => 'servicio_simple',
            'prioridad' => 'Media',
            'estado' => 'Pendiente',
            'tipo_pago' => 'efectivo',
            'precio' => 150,
            'costo_operativo' => 25,
            'descripcion' => 'Orden de prueba',
            'descripcion_servicio' => 'Servicio tecnico',
            'moneda' => 'MXN',
            'tasa_cambio' => 1,
            'productos' => [],
            'precio_escrito' => '',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $orden = OrdenServicio::latest('id_orden_servicio')->first();

    expect($orden)->not->toBeNull()
        ->and($orden->precio_escrito)->toContain('PESOS')
        ->and($orden->precio_escrito)->toContain('M.N.');
});

it('guarda la cantidad por escrito en el borrador del acta del gerente', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'precio' => 200,
        'costo_operativo' => 10,
        'impuestos' => 32,
        'servicio' => 'Mantenimiento preventivo',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('ordenes.acta.borrador', $orden->id_orden_servicio), [
            'responsable' => 'Cliente Prueba',
            'puesto' => 'Compras',
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'trabajo_realizado' => 'Se realizo el servicio correctamente.',
            'conforme' => 'si',
            'cantidad_escrita' => 'DOSCIENTOS CUARENTA Y DOS PESOS 00/100 M.N.',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $orden = $orden->fresh();

    expect(data_get($orden->acta_data, 'cantidad_escrita'))
        ->toBe('DOSCIENTOS CUARENTA Y DOS PESOS 00/100 M.N.');
});

it('bloquea extras via api gerente en ordenes finalizadas sin acta firmada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'estado' => 'Finalizado',
        'acta_estado' => null,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('api.ordenes.extras.store', $orden->id_orden_servicio), [
            'descripcion' => 'Material urgente',
            'cantidad' => 1,
            'precio_unitario' => 99,
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'No se pueden modificar extras en servicios finalizados o con acta firmada.',
        ]);

    $this->assertDatabaseCount('orden_material_extra', 0);
});

it('bloquea extras del tecnico cuando la orden ya esta finalizada aunque no tenga acta firmada', function () {
    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
    ]);

    $orden = crearOrden([
        'id_tecnico' => $tecnico->id,
        'estado' => 'Finalizado',
        'acta_estado' => null,
    ]);

    $response = $this
        ->actingAs($tecnico)
        ->from(route('tecnico.detalles', $orden->id_orden_servicio))
        ->post(route('tecnico.ordenes.extras.store', $orden->id_orden_servicio), [
            'descripcion' => 'Material urgente',
            'cantidad' => 2,
        ]);

    $response
        ->assertRedirect(route('tecnico.detalles', $orden->id_orden_servicio))
        ->assertSessionHas('error', 'La orden ya esta finalizada y no se pueden registrar mas materiales no previstos.');

    $this->assertDatabaseCount('orden_material_extra', 0);
});

function crearCliente(array $attributes = []): Cliente
{
    static $seq = 1;

    $index = $seq++;

    return Cliente::create(array_merge([
        'codigo_cliente' => 'CLI-' . $index,
        'nombre' => 'Cliente ' . $index,
        'nombre_empresa' => 'Empresa ' . $index,
        'direccion_fiscal' => 'Calle Falsa 123',
        'contacto' => 'Contacto ' . $index,
        'telefono' => '555000' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'contacto_adicional' => null,
        'correo_electronico' => 'cliente' . $index . '@example.com',
        'datos_fiscales' => 'RFC' . $index,
        'ubicacion' => 'CDMX',
    ], $attributes));
}

function crearOrden(array $attributes = []): OrdenServicio
{
    $cliente = $attributes['cliente'] ?? crearCliente();
    unset($attributes['cliente']);

    return OrdenServicio::create(array_merge([
        'id_cliente' => $cliente->clave_cliente,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'En proceso',
        'precio' => 0,
        'costo_operativo' => 0,
        'impuestos' => 0,
        'moneda' => 'MXN',
        'tasa_cambio' => 1,
        'tipo_pago' => 'efectivo',
        'tipo_orden' => 'servicio_simple',
        'servicio' => 'Revision',
    ], $attributes));
}
