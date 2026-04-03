<?php

use App\Exports\Ordenes\OrdenesServicioExport;
use App\Exports\Reportes\SalidasInventarioExport;
use App\Models\Cliente;
use App\Models\CreditoCliente;
use App\Models\DetalleOrdenProducto;
use App\Models\OrdenServicio;
use App\Models\PagoCredito;
use App\Models\Producto;
use App\Reports\SalidasInventarioReport;
use App\Reports\VentasReport;
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

it('guarda el estado de facturacion al crear una orden', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearCliente();

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('ordenes.store'), [
            'id_cliente' => $cliente->clave_cliente,
            'servicio' => 'Servicio facturado',
            'tipo_orden' => 'servicio_simple',
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
            'tipo_pago' => 'transferencia',
            'facturado' => 1,
            'precio' => 100,
            'costo_operativo' => 10,
            'descripcion' => 'Orden con facturacion',
            'descripcion_servicio' => 'Prueba de facturacion',
            'moneda' => 'MXN',
            'tasa_cambio' => 1,
            'productos' => [],
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $orden = OrdenServicio::latest('id_orden_servicio')->first();

    expect($orden)->not->toBeNull()
        ->and($orden->facturado)->toBeTrue()
        ->and($orden->facturacion_label)->toBe('Facturado');
});

it('permite actualizar la facturacion desde el index de ordenes', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'facturado' => false,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->from(route('ordenes.index'))
        ->patch(route('ordenes.facturacion.update', $orden->id_orden_servicio), [
            'facturado' => 1,
        ]);

    $response->assertRedirect(route('ordenes.index'));

    expect($orden->fresh()->facturado)->toBeTrue()
        ->and($orden->fresh()->facturacion_label)->toBe('Facturado');
});

it('permite actualizar la facturacion aunque la orden tenga acta firmada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'estado' => 'Finalizado',
        'acta_estado' => 'firmada',
        'acta_firmada_at' => now(),
        'facturado' => false,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->from(route('ordenes.index'))
        ->patch(route('ordenes.facturacion.update', $orden->id_orden_servicio), [
            'facturado' => 1,
        ]);

    $response
        ->assertRedirect(route('ordenes.index'))
        ->assertSessionHas('success', 'Estado de facturacion actualizado correctamente.');

    expect($orden->fresh()->facturado)->toBeTrue()
        ->and($orden->fresh()->facturacion_label)->toBe('Facturado');
});

it('muestra la facturacion junto al estado en el index de ordenes', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'estado' => 'Finalizado',
        'facturado' => true,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->get(route('ordenes.index'));

    $response
        ->assertOk()
        ->assertSeeInOrder([
            $orden->folio,
            'Finalizado',
            'Facturado',
        ], false);
});

it('incluye la facturacion en el reporte de ventas y permite exportar ordenes', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'estado' => 'Completada',
        'facturado' => true,
        'precio' => 350,
        'costo_operativo' => 25,
        'impuestos' => 56,
        'anticipo_mxn' => 100,
    ]);

    $reporte = app(VentasReport::class)->build(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    expect($reporte['cols'])->toContain('Facturacion')
        ->and($reporte['rows'])->not->toBeEmpty()
        ->and($reporte['rows'][0]['Facturacion'] ?? null)->toBe('Facturado');

    $export = new OrdenesServicioExport(
        collect([$orden->fresh(['cliente', 'tecnico', 'tecnicos'])]),
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay(),
    );

    expect($export->headings())->not->toContain('Fecha finalizacion');
    expect(array_slice($export->headings(), -3))->toContain('Facturacion');

    $response = $this
        ->actingAs($gerente)
        ->get(route('ordenes.export', [
            'desde' => Carbon::today()->toDateString(),
            'hasta' => Carbon::today()->toDateString(),
        ]));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('usa el total final y marca pagadas las ordenes completadas que no son a credito en el reporte de ventas', function () {
    $orden = crearOrden([
        'estado' => 'Completada',
        'tipo_pago' => 'efectivo',
        'precio' => 100,
        'costo_operativo' => 10,
        'impuestos' => 16,
        'total_adicional_mxn' => 24,
        'anticipo_mxn' => 50,
        'facturado' => true,
    ]);

    $reporte = app(VentasReport::class)->build(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    $row = collect($reporte['rows'])->firstWhere('Orden', $orden->id_orden_servicio);

    expect($row)->not->toBeNull()
        ->and($row['Costo servicio'])->toBe('100.00')
        ->and($row['Costo operativo'])->toBe('10.00')
        ->and($row['Impuestos'])->toBe('16.00')
        ->and($row['Total orden'])->toBe('$150.00')
        ->and($row['Total pagado'])->toBe('$150.00')
        ->and($row['Saldo'])->toBe('0.00')
        ->and($reporte['meta']['totales']['general']['mxn'])->toBe(150.0)
        ->and($reporte['meta']['totales']['pagado']['mxn'])->toBe(150.0)
        ->and($reporte['meta']['totales']['impuestos']['mxn'])->toBe(16.0);
});

it('solo considera pagada una orden a credito cuando el credito fue saldado en el reporte de ventas', function () {
    $cliente = crearCliente();

    CreditoCliente::create([
        'clave_cliente' => $cliente->clave_cliente,
        'monto_maximo' => 5000,
        'monto_usado' => 0,
        'dias_credito' => 30,
        'fecha_asignacion' => Carbon::today()->toDateString(),
        'estatus' => 'activo',
    ]);

    $orden = crearOrden([
        'cliente' => $cliente,
        'estado' => 'Completada',
        'tipo_pago' => 'credito_cliente',
        'precio' => 100,
        'costo_operativo' => 10,
        'impuestos' => 16,
        'total_adicional_mxn' => 24,
        'anticipo_mxn' => 50,
    ]);

    $reporteAntes = app(VentasReport::class)->build(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    $rowAntes = collect($reporteAntes['rows'])->firstWhere('Orden', $orden->id_orden_servicio);

    expect($rowAntes)->not->toBeNull()
        ->and($rowAntes['Total orden'])->toBe('$150.00')
        ->and($rowAntes['Total pagado'])->toBe('$50.00')
        ->and($rowAntes['Saldo'])->toBe('100.00');

    PagoCredito::create([
        'clave_cliente' => $cliente->clave_cliente,
        'monto' => 100,
        'fecha' => Carbon::today()->toDateString(),
        'descripcion' => 'Liquidacion de credito',
    ]);

    $reporteDespues = app(VentasReport::class)->build(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    $rowDespues = collect($reporteDespues['rows'])->firstWhere('Orden', $orden->id_orden_servicio);

    expect($rowDespues)->not->toBeNull()
        ->and($rowDespues['Total pagado'])->toBe('$150.00')
        ->and($rowDespues['Saldo'])->toBe('0.00');
});

it('carga datos del api de seguimiento para la quincena solicitada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $orden = crearOrden([
        'fecha_orden' => Carbon::parse('2026-04-02')->toDateString(),
        'precio' => 150,
        'costo_operativo' => 25,
        'impuestos' => 28,
        'prioridad' => 'Alta',
        'facturado' => false,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->getJson(route('api.seguimiento-servicios', [
            'desde' => '2026-04-01',
            'hasta' => '2026-04-15',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('rows.0.id', $orden->id_orden_servicio)
        ->assertJsonPath('rows.0.orderId', 'OS-' . $orden->id_orden_servicio)
        ->assertJsonPath('rows.0.facturacion', 'No facturado');
});

it('resume solo el monto de las ordenes facturadas en seguimiento', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    crearOrden([
        'fecha_orden' => Carbon::parse('2026-04-02')->toDateString(),
        'precio' => 100,
        'costo_operativo' => 10,
        'impuestos' => 16,
        'total_adicional_mxn' => 24,
        'facturado' => true,
    ]);

    crearOrden([
        'fecha_orden' => Carbon::parse('2026-04-03')->toDateString(),
        'precio' => 200,
        'costo_operativo' => 20,
        'impuestos' => 32,
        'total_adicional_mxn' => 48,
        'facturado' => false,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->getJson(route('api.seguimiento-servicios', [
            'desde' => '2026-04-01',
            'hasta' => '2026-04-15',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('summary.facturadas', 1)
        ->assertJsonPath('summary.totalFacturado', 150);
});

it('mantiene columnas legibles y consistentes en el reporte de salidas de inventario', function () {
    $cliente = crearCliente();

    $producto = Producto::create([
        'nombre' => 'Bobina de cable',
        'numero_parte' => 'SAL-001',
        'unidad' => 'pieza',
        'categoria' => 'Cableado',
        'descripcion' => 'Producto para reporte de salidas',
        'stock_seguridad' => 0,
        'stock_total' => 10,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 10,
        'activo' => true,
    ]);

    $orden = crearOrden([
        'cliente' => $cliente,
        'estado' => 'Completada',
        'moneda' => 'MXN',
    ]);

    DetalleOrdenProducto::create([
        'id_orden_servicio' => $orden->id_orden_servicio,
        'codigo_producto' => $producto->codigo_producto,
        'nombre_producto' => $producto->nombre,
        'cantidad' => 2,
        'precio_unitario' => 125,
        'total' => 250,
    ]);

    $reporte = app(SalidasInventarioReport::class)->build(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    expect($reporte['cols'])->toBe([
        'ID detalle',
        'Fecha de salida',
        'Hora de salida',
        'Numero de parte',
        'Nombre producto',
        'Cantidad',
        'Precio unitario',
        'Total',
        'Moneda',
        'Numeros de serie',
    ]);

    $row = collect($reporte['rows'])->first();

    expect($row)->not->toBeNull()
        ->and($row['Numero de parte'] ?? null)->toBe('SAL-001')
        ->and($row['Nombre producto'] ?? null)->toBe('Bobina de cable')
        ->and($row['Numeros de serie'] ?? null)->toBe('-');

    $export = new SalidasInventarioExport(
        Carbon::today()->startOfDay(),
        Carbon::today()->endOfDay()
    );

    expect($export->headings())->toBe($reporte['cols']);
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
