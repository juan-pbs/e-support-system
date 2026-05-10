<?php

use App\Models\Cliente;
use App\Models\ClienteDireccionLogistica;
use App\Models\Inventario;
use App\Models\JornadaLogistica;
use App\Models\MovimientoLogistico;
use App\Models\OrderMaintenanceHistory;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Logistica\LogisticaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('guarda varias direcciones logisticas del cliente y sincroniza la principal a la ubicacion legacy', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('clientes.store'), [
            'codigo_cliente' => 'CLI-LOG-01',
            'nombre' => 'Cliente Logistica',
            'empresa' => 'Cliente Logistica SA',
            'telefono' => '4421234567',
            'correo' => 'cliente.logistica@example.com',
            'direccion_fiscal' => 'Av. Tecnologico 10',
            'contacto' => 'Maria Ruta',
            'redirect_to' => route('clientes'),
            'direcciones_logisticas' => [
                [
                    'alias' => 'Matriz',
                    'direccion_formateada' => 'Av. Constituyentes 100, Queretaro, Qro.',
                    'place_id' => 'place-matriz',
                    'latitud' => 20.588793,
                    'longitud' => -100.389888,
                    'referencia' => 'Frente al parque',
                    'predeterminada' => 1,
                    'verificada_en_mapa' => 1,
                    'metodo_verificacion' => 'autocomplete',
                ],
                [
                    'alias' => 'Bodega',
                    'direccion_formateada' => 'Calle 5 de Febrero 500, Queretaro, Qro.',
                    'place_id' => 'place-bodega',
                    'latitud' => 20.61234,
                    'longitud' => -100.40123,
                    'referencia' => 'Entrada lateral',
                    'predeterminada' => 0,
                    'verificada_en_mapa' => 1,
                    'metodo_verificacion' => 'mapa',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('clientes'))
        ->assertSessionHas('success', 'Cliente registrado correctamente.');

    $cliente = Cliente::query()->where('codigo_cliente', 'CLI-LOG-01')->firstOrFail();

    expect($cliente->fresh()->ubicacion)->toBe('Av. Constituyentes 100, Queretaro, Qro.')
        ->and($cliente->direccionesLogisticas()->count())->toBe(2);

    $this->assertDatabaseHas('cliente_direcciones_logisticas', [
        'clave_cliente' => $cliente->clave_cliente,
        'alias' => 'Matriz',
        'predeterminada' => 1,
        'verificada_en_mapa' => 1,
    ]);
});

it('permite guardar clientes sin direccion logistica', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('clientes.store'), [
            'codigo_cliente' => 'CLI-SIN-LOG',
            'nombre' => 'Cliente Sin Logistica',
            'empresa' => 'Cliente Sin Logistica SA',
            'telefono' => '4421234999',
            'correo' => 'cliente.sin.logistica@example.com',
            'direccion_fiscal' => 'Av. Tecnologico 30',
            'contacto' => 'Persona Cliente',
            'redirect_to' => route('clientes'),
        ]);

    $response
        ->assertRedirect(route('clientes'))
        ->assertSessionHas('success', 'Cliente registrado correctamente.');

    $cliente = Cliente::query()->where('codigo_cliente', 'CLI-SIN-LOG')->firstOrFail();

    expect($cliente->direccionesLogisticas()->count())->toBe(0)
        ->and($cliente->ubicacion)->toBeNull();
});

it('guarda la direccion logistica verificada del proveedor', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('proveedores.guardar'), [
            'nombre' => 'Proveedor Logistica',
            'rfc' => 'XAXX010101000',
            'alias' => 'PL Centro',
            'direccion' => '',
            'direccion_logistica' => 'Av. Universidad 200, Queretaro, Qro.',
            'direccion_logistica_place_id' => 'prov-place-1',
            'direccion_logistica_latitud' => 20.59321,
            'direccion_logistica_longitud' => -100.39234,
            'direccion_logistica_referencia' => 'Puerta 2',
            'direccion_logistica_verificada_en_mapa' => 1,
            'direccion_logistica_metodo' => 'autocomplete',
            'contacto' => 'Rogelio Proveedor',
            'telefono' => '4427654321',
            'correo' => 'proveedor.logistica@example.com',
        ]);

    $response
        ->assertRedirect(route('proveedores.index'))
        ->assertSessionHas('success', 'Proveedor registrado correctamente.');

    $proveedor = Proveedor::query()->where('rfc', 'XAXX010101000')->firstOrFail();

    expect($proveedor->direccion_logistica)->toBe('Av. Universidad 200, Queretaro, Qro.')
        ->and($proveedor->direccion)->toBe('Av. Universidad 200, Queretaro, Qro.')
        ->and($proveedor->direccion_logistica_verificada_en_mapa)->toBeTrue();
});

it('permite guardar proveedores sin direccion logistica', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('proveedores.guardar'), [
            'nombre' => 'Proveedor Sin Logistica',
            'rfc' => 'XAXX010101001',
            'alias' => 'Sin logistica',
            'contacto' => 'Persona Proveedor',
            'telefono' => '4427654999',
            'correo' => 'proveedor.sin.logistica@example.com',
        ]);

    $response
        ->assertRedirect(route('proveedores.index'))
        ->assertSessionHas('success', 'Proveedor registrado correctamente.');

    $proveedor = Proveedor::query()->where('rfc', 'XAXX010101001')->firstOrFail();

    expect($proveedor->direccion_logistica)->toBeNull()
        ->and($proveedor->direccion)->toBeNull()
        ->and($proveedor->direccion_logistica_verificada_en_mapa)->toBeFalse();
});

it('permite ir de inventario a editar proveedor sin direccion y regresar a la entrada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $producto = crearProductoLogistico();
    $proveedor = crearProveedorLogistico([
        'direccion' => null,
        'direccion_logistica' => null,
        'direccion_logistica_place_id' => null,
        'direccion_logistica_latitud' => null,
        'direccion_logistica_longitud' => null,
        'direccion_logistica_referencia' => null,
        'direccion_logistica_verificada_en_mapa' => false,
        'direccion_logistica_metodo' => null,
    ]);

    $entradaUrl = route('inventario.entrada', $producto->codigo_producto);

    $this
        ->actingAs($gerente)
        ->get($entradaUrl)
        ->assertOk()
        ->assertSee('Este proveedor no tiene direcci', false)
        ->assertSee(route('proveedores.editar', [
            'id' => $proveedor->clave_proveedor,
            'redirect' => $entradaUrl,
        ]), false);

    $this
        ->actingAs($gerente)
        ->put(route('proveedores.actualizar', $proveedor->clave_proveedor), [
            'redirect_to' => $entradaUrl,
            'nombre' => $proveedor->nombre,
            'rfc' => $proveedor->rfc,
            'alias' => $proveedor->alias,
            'direccion_logistica' => 'Av. Inventario 100, Queretaro, Qro.',
            'direccion_logistica_place_id' => 'inventario-prov-place',
            'direccion_logistica_latitud' => 20.601,
            'direccion_logistica_longitud' => -100.401,
            'direccion_logistica_referencia' => 'Acceso de almacen',
            'direccion_logistica_verificada_en_mapa' => 1,
            'direccion_logistica_metodo' => 'autocomplete',
            'contacto' => $proveedor->contacto,
            'telefono' => $proveedor->telefono,
            'correo' => $proveedor->correo,
        ])
        ->assertRedirect($entradaUrl)
        ->assertSessionHas('success', 'Proveedor actualizado correctamente.');

    expect($proveedor->fresh()->direccion_logistica_verificada_en_mapa)->toBeTrue();
});

it('registra historial al reabrir y permite cerrar de nuevo conservando firmas de la orden', function () {
    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $cliente = crearClienteLogistico();
    $firmaFecha = now()->subDay();

    $orden = OrdenServicio::query()->create([
        'id_cliente' => $cliente->clave_cliente,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'Completada',
        'prioridad' => 'Media',
        'servicio' => 'Servicio con acta',
        'descripcion_servicio' => 'Servicio cerrado para prueba',
        'precio' => 100,
        'costo_operativo' => 10,
        'tipo_pago' => 'efectivo',
        'tipo_orden' => 'servicio_simple',
        'acta_estado' => 'firmada',
        'acta_firmada_at' => $firmaFecha,
        'acta_pdf_path' => 'actas/acta_prueba.pdf',
        'acta_pdf_hash' => 'hash-prueba',
        'firma_conformidad' => 'firma-conformidad-previa',
        'firma_resp_path' => 'firmas/responsable.png',
        'firma_emp_path' => 'firmas/empresa.png',
    ]);

    $this
        ->actingAs($sistema)
        ->post(route('sistema.mantenimiento.ordenes.reabrir'), [
            'orden_id' => $orden->id_orden_servicio,
            'reason' => 'Corregir datos',
        ])
        ->assertRedirect(route('sistema.mantenimiento'));

    $orden->refresh();

    expect($orden->estado)->toBe('En proceso')
        ->and($orden->acta_estado)->toBe('borrador')
        ->and($orden->acta_firmada_at?->toDateTimeString())->toBe($firmaFecha->toDateTimeString())
        ->and($orden->firma_conformidad)->toBe('firma-conformidad-previa')
        ->and($orden->firma_resp_path)->toBe('firmas/responsable.png')
        ->and(OrderMaintenanceHistory::query()->where('orden_id', $orden->id_orden_servicio)->where('action', 'reopen_order')->count())->toBe(1);

    $this
        ->actingAs($sistema)
        ->post(route('sistema.mantenimiento.ordenes.cerrar'), [
            'orden_id' => $orden->id_orden_servicio,
            'reason' => 'Correccion terminada',
        ])
        ->assertRedirect(route('sistema.mantenimiento'));

    $orden->refresh();

    expect($orden->estado)->toBe('Completada')
        ->and($orden->acta_estado)->toBe('firmada')
        ->and($orden->acta_pdf_path)->toBe('actas/acta_prueba.pdf')
        ->and($orden->acta_pdf_hash)->toBe('hash-prueba')
        ->and($orden->firma_conformidad)->toBe('firma-conformidad-previa')
        ->and($orden->firma_resp_path)->toBe('firmas/responsable.png')
        ->and($orden->firma_emp_path)->toBe('firmas/empresa.png')
        ->and(OrderMaintenanceHistory::query()->where('orden_id', $orden->id_orden_servicio)->count())->toBe(2);
});

it('rechaza direcciones de cliente que no quedaron verificadas en mapa', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->from(route('clientes.nuevo'))
        ->post(route('clientes.store'), [
            'codigo_cliente' => 'CLI-LOG-ERR',
            'nombre' => 'Cliente Sin Validar',
            'empresa' => 'Cliente Sin Validar SA',
            'telefono' => '4421234000',
            'correo' => 'cliente.sin.validar@example.com',
            'direccion_fiscal' => 'Av. Tecnologico 20',
            'contacto' => 'Maria Ruta',
            'redirect_to' => route('clientes'),
            'direcciones_logisticas' => [
                [
                    'alias' => 'Principal',
                    'direccion_formateada' => 'Dirección escrita manualmente',
                    'place_id' => '',
                    'latitud' => '',
                    'longitud' => '',
                    'referencia' => 'Sin mapa',
                    'predeterminada' => 1,
                    'verificada_en_mapa' => 0,
                    'metodo_verificacion' => '',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('clientes.nuevo'))
        ->assertSessionHasErrors([
            'direcciones_logisticas.0.place_id',
            'direcciones_logisticas.0.verificada_en_mapa',
        ]);
});

it('rechaza proveedores sin direccion logistica verificada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->from(route('proveedores.nuevo'))
        ->post(route('proveedores.guardar'), [
            'nombre' => 'Proveedor Sin Mapa',
            'rfc' => 'XEXX010101000',
            'alias' => 'Sin mapa',
            'direccion_logistica' => 'Dirección capturada a mano',
            'direccion_logistica_place_id' => '',
            'direccion_logistica_latitud' => '',
            'direccion_logistica_longitud' => '',
            'direccion_logistica_referencia' => 'Texto libre',
            'direccion_logistica_verificada_en_mapa' => 0,
            'direccion_logistica_metodo' => '',
            'contacto' => 'Persona Proveedor',
            'telefono' => '4427654000',
            'correo' => 'proveedor.sin.mapa@example.com',
        ]);

    $response
        ->assertRedirect(route('proveedores.nuevo'))
        ->assertSessionHasErrors([
            'direccion_logistica_place_id',
            'direccion_logistica_verificada_en_mapa',
        ]);
});

it('consulta sugerencias de direcciones logisticas desde el backend', function () {
    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([
            [
                'display_name' => 'Av. Universidad 200, Santiago de Querétaro, Qro., México',
                'lat' => '20.59321',
                'lon' => '-100.39234',
                'osm_type' => 'way',
                'osm_id' => 12345,
            ],
        ], 200),
    ]);

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->get(route('logistica.direcciones.search', [
            'q' => 'Av Universidad 200 Queretaro',
        ]));

    $response
        ->assertOk()
        ->assertJsonFragment([
            'display_name' => 'Av. Universidad 200, Santiago de Querétaro, Qro., México',
        ]);
});

it('convierte un punto del mapa a direccion usando el backend', function () {
    Http::fake([
        'https://nominatim.openstreetmap.org/reverse*' => Http::response([
            'display_name' => 'Blvd. Benito Juárez 35, Santiago de Querétaro, Qro., México',
            'lat' => '20.61398',
            'lon' => '-100.40132',
            'osm_type' => 'way',
            'osm_id' => 67890,
        ], 200),
    ]);

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->get(route('logistica.direcciones.reverse', [
            'lat' => 20.61398,
            'lng' => -100.40132,
        ]));

    $response
        ->assertOk()
        ->assertJsonFragment([
            'display_name' => 'Blvd. Benito Juárez 35, Santiago de Querétaro, Qro., México',
        ]);
});

it('programa una recoleccion logistica sin afectar el inventario hasta confirmar la recepcion', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Ruta 1',
    ]);

    $producto = crearProductoLogistico();
    $proveedor = crearProveedorLogistico();
    $jornada = crearJornadaLogistica();

    $response = $this
        ->actingAs($gerente)
        ->post(route('entrada.store'), [
            'codigo_producto' => $producto->codigo_producto,
            'clave_proveedor' => $proveedor->clave_proveedor,
            'costo' => 100,
            'precio' => 150,
            'tipo_control' => 'PIEZAS',
            'cantidad_ingresada' => 3,
            'forma_ingreso' => 'recoleccion_programada',
            'jornada_logistica_id' => $jornada->id,
            'tecnico_logistica_id' => $tecnico->id,
            'fecha_programada' => now()->toDateString(),
            'hora_programada' => '10:30',
            'observaciones_logistica' => 'Recoger en mostrador principal',
        ]);

    $response
        ->assertRedirect(route('inventario'))
        ->assertSessionHas('success', 'Se programó la recolección logística. El stock se actualizará cuando el gerente confirme la recepción.');

    expect(Inventario::query()->count())->toBe(0);

    $movimiento = MovimientoLogistico::query()->with('detalles')->firstOrFail();

    expect($movimiento->tipo)->toBe('recoleccion')
        ->and($movimiento->estado)->toBe('pendiente')
        ->and($movimiento->clave_proveedor)->toBe($proveedor->clave_proveedor)
        ->and($movimiento->tecnico_id)->toBe($tecnico->id)
        ->and($movimiento->detalles)->toHaveCount(1)
        ->and((float) $movimiento->detalles->first()->cantidad)->toBe(3.0);
});

it('muestra recolecciones de inventario disponibles a cualquier tecnico y la toma el primero que la avanza', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnicoA = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Disponible A',
    ]);

    $tecnicoB = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Disponible B',
    ]);

    $producto = crearProductoLogistico([
        'nombre' => 'Router logística',
        'numero_parte' => 'LOG-ANY-001',
    ]);
    $proveedor = crearProveedorLogistico([
        'direccion_logistica_latitud' => 20.60111,
        'direccion_logistica_longitud' => -100.38821,
    ]);
    $jornada = crearJornadaLogistica();

    /** @var LogisticaService $service */
    $service = app(LogisticaService::class);
    $movimiento = $service->programarRecoleccionInventario([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => $proveedor->clave_proveedor,
        'costo' => 180,
        'precio' => 240,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 2,
        'forma_ingreso' => 'recoleccion_programada',
        'jornada_logistica_id' => $jornada->id,
        'fecha_programada' => now()->toDateString(),
        'hora_programada' => '09:15',
        'observaciones_logistica' => 'Disponible para cualquier técnico',
    ], $gerente);

    expect($movimiento->tecnico_id)->toBeNull();

    $this
        ->actingAs($tecnicoA)
        ->get(route('tecnico.logistica.index'))
        ->assertOk()
        ->assertSee($movimiento->alias_direccion)
        ->assertSee('Disponible para tomar');

    $this
        ->actingAs($tecnicoA)
        ->get(route('tecnico.logistica.show', $movimiento))
        ->assertOk()
        ->assertSee('Movimiento #' . $movimiento->id);

    $this
        ->actingAs($tecnicoA)
        ->post(route('tecnico.logistica.estado', $movimiento), [
            'estado' => 'en_ruta',
        ])
        ->assertRedirect(route('tecnico.logistica.show', $movimiento))
        ->assertSessionHas('success', 'Estado logístico actualizado correctamente.');

    expect($movimiento->fresh()->tecnico_id)->toBe($tecnicoA->id)
        ->and($movimiento->fresh()->estado)->toBe('en_ruta');

    $this
        ->actingAs($tecnicoB)
        ->get(route('tecnico.logistica.index'))
        ->assertOk()
        ->assertDontSee($movimiento->alias_direccion);

    $this
        ->actingAs($tecnicoB)
        ->get(route('tecnico.logistica.show', $movimiento))
        ->assertForbidden();
});

it('permite al tecnico completar una recoleccion con su ubicacion actual y al gerente confirmarla en inventario', function () {
    Storage::fake('public');

    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Ruta 2',
    ]);

    $producto = crearProductoLogistico([
        'nombre' => 'Switch administrable',
        'numero_parte' => 'LOG-002',
    ]);
    $proveedor = crearProveedorLogistico([
        'direccion_logistica_latitud' => 20.600001,
        'direccion_logistica_longitud' => -100.390001,
    ]);
    $jornada = crearJornadaLogistica();

    /** @var LogisticaService $service */
    $service = app(LogisticaService::class);
    $movimiento = $service->programarRecoleccionInventario([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => $proveedor->clave_proveedor,
        'costo' => 250,
        'precio' => 390,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 4,
        'forma_ingreso' => 'recoleccion_programada',
        'jornada_logistica_id' => $jornada->id,
        'tecnico_logistica_id' => $tecnico->id,
        'fecha_programada' => now()->toDateString(),
        'hora_programada' => '11:00',
        'observaciones_logistica' => 'Tomar evidencia completa',
    ], $gerente);

    $this
        ->actingAs($tecnico)
        ->get(route('tecnico.logistica.index'))
        ->assertOk()
        ->assertSee('Ruta logística')
        ->assertSee($movimiento->alias_direccion);

    $this
        ->actingAs($tecnico)
        ->get(route('tecnico.logistica.show', $movimiento))
        ->assertOk()
        ->assertSee('Movimiento #' . $movimiento->id)
        ->assertSee('Mapa del punto');

    $responseTecnico = $this
        ->actingAs($tecnico)
        ->post(route('tecnico.logistica.completar', $movimiento), [
            'comentario' => 'Producto recogido sin incidencias',
            'current_lat' => 20.600001,
            'current_lng' => -100.390001,
            'evidencias' => [
                UploadedFile::fake()->image('fachada.jpg'),
                UploadedFile::fake()->image('producto.jpg'),
            ],
        ]);

    $responseTecnico
        ->assertRedirect(route('tecnico.logistica.show', $movimiento))
        ->assertSessionHas('success', 'Movimiento completado correctamente.');

    expect($movimiento->fresh()->estado)->toBe('recogido')
        ->and($movimiento->fresh()->evidencias()->count())->toBe(2);

    $responseGerencia = $this
        ->actingAs($gerente)
        ->post(route('logistica.movimientos.confirmarRecepcion', $movimiento));

    $responseGerencia
        ->assertRedirect(route('logistica.index'))
        ->assertSessionHas('success', 'La recolección ya fue confirmada como entrada de inventario.');

    $entrada = Inventario::query()->firstOrFail();
    $producto->refresh();

    expect($entrada->movimiento_logistico_id)->toBe($movimiento->id)
        ->and($entrada->forma_ingreso)->toBe('recoleccion_programada')
        ->and($entrada->estado_recepcion)->toBe('recibido')
        ->and((int) $producto->stock_total)->toBe(4);
});

it('permite completar un movimiento logistico sin adjuntar imagenes', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Sin Evidencias',
    ]);

    $producto = crearProductoLogistico([
        'nombre' => 'Access point demo',
        'numero_parte' => 'LOG-005',
    ]);
    $proveedor = crearProveedorLogistico([
        'direccion_logistica_latitud' => 20.600501,
        'direccion_logistica_longitud' => -100.390401,
    ]);
    $jornada = crearJornadaLogistica();

    /** @var LogisticaService $service */
    $service = app(LogisticaService::class);
    $movimiento = $service->programarRecoleccionInventario([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => $proveedor->clave_proveedor,
        'costo' => 300,
        'precio' => 450,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 1,
        'forma_ingreso' => 'recoleccion_programada',
        'jornada_logistica_id' => $jornada->id,
        'tecnico_logistica_id' => $tecnico->id,
        'fecha_programada' => now()->toDateString(),
        'hora_programada' => '13:00',
    ], $gerente);

    $response = $this
        ->actingAs($tecnico)
        ->post(route('tecnico.logistica.completar', $movimiento), [
            'comentario' => 'Cierre sin fotos',
            'current_lat' => 20.600501,
            'current_lng' => -100.390401,
        ]);

    $response
        ->assertRedirect(route('tecnico.logistica.show', $movimiento))
        ->assertSessionHas('success', 'Movimiento completado correctamente.');

    expect($movimiento->fresh()->estado)->toBe('recogido')
        ->and($movimiento->fresh()->evidencias()->count())->toBe(0);
});

it('crea automaticamente un movimiento de entrega cuando la orden requiere logistica', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Entrega',
    ]);

    $cliente = crearClienteLogistico();
    $direccion = ClienteDireccionLogistica::query()->create([
        'clave_cliente' => $cliente->clave_cliente,
        'alias' => 'Sucursal Centro',
        'direccion_formateada' => 'Av. Zaragoza 45, Queretaro, Qro.',
        'place_id' => 'client-place-1',
        'latitud' => 20.5895,
        'longitud' => -100.3883,
        'referencia' => 'Recepción principal',
        'activa' => true,
        'predeterminada' => true,
        'verificada_en_mapa' => true,
        'metodo_verificacion' => 'autocomplete',
    ]);

    $producto = crearProductoLogistico([
        'nombre' => 'Camara PTZ',
        'numero_parte' => 'LOG-003',
    ]);
    Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 120,
        'precio' => 175,
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
    crearJornadaLogistica();

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('ordenes.store'), [
            'id_cliente' => $cliente->clave_cliente,
            'cliente_direccion_id' => $direccion->id,
            'servicio' => 'Entrega de equipo',
            'tipo_orden' => 'compra',
            'prioridad' => 'Media',
            'estado' => 'Pendiente',
            'requiere_logistica' => 1,
            'id_tecnico' => $tecnico->id,
            'tecnicos_ids' => [$tecnico->id],
            'tipo_pago' => 'efectivo',
            'precio' => 350,
            'costo_operativo' => 0,
            'descripcion' => 'Entrega programada desde logística',
            'descripcion_servicio' => 'Equipo listo para salida',
            'moneda' => 'MXN',
            'tasa_cambio' => 1,
            'productos' => [
                [
                    'codigo_producto' => $producto->codigo_producto,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => 2,
                    'precio' => 175,
                ],
            ],
            'precio_escrito' => '',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $orden = OrdenServicio::query()->latest('id_orden_servicio')->firstOrFail();
    $movimiento = MovimientoLogistico::query()
        ->with('detalles')
        ->where('orden_servicio_id', $orden->id_orden_servicio)
        ->where('tipo', 'entrega')
        ->firstOrFail();

    expect($movimiento->estado)->toBe('pendiente')
        ->and($movimiento->clave_cliente)->toBe($cliente->clave_cliente)
        ->and($movimiento->cliente_direccion_id)->toBe($direccion->id)
        ->and($movimiento->tecnico_id)->toBeNull()
        ->and($movimiento->detalles)->toHaveCount(1)
        ->and((float) $movimiento->detalles->first()->cantidad)->toBe(2.0);

    $this
        ->actingAs($gerente)
        ->get(route('logistica.index'))
        ->assertOk()
        ->assertSee('Logística')
        ->assertSee('Sucursal Centro')
        ->assertSee('Entrega');

    $this
        ->actingAs($tecnico)
        ->get(route('tecnico.logistica.index'))
        ->assertOk()
        ->assertSee('Disponible para tomar')
        ->assertSee('Sucursal Centro');

    $this
        ->actingAs($tecnico)
        ->post(route('tecnico.logistica.estado', $movimiento), [
            'estado' => 'en_ruta',
        ])
        ->assertRedirect(route('tecnico.logistica.show', $movimiento));

    expect($movimiento->fresh()->tecnico_id)->toBe($tecnico->id);
});

it('no crea movimientos de entrega para ordenes que no son entrega venta aunque marquen logistica', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Servicio',
    ]);

    $cliente = crearClienteLogistico([
        'codigo_cliente' => 'CLI-LOG-SVC',
        'nombre' => 'Cliente Servicio',
        'nombre_empresa' => 'Cliente Servicio SA',
    ]);
    $direccion = ClienteDireccionLogistica::query()->create([
        'clave_cliente' => $cliente->clave_cliente,
        'alias' => 'Oficina',
        'direccion_formateada' => 'Av. Constituyentes 88, Queretaro, Qro.',
        'place_id' => 'client-service-place-1',
        'latitud' => 20.5884,
        'longitud' => -100.3901,
        'referencia' => 'Recepción frontal',
        'activa' => true,
        'predeterminada' => true,
        'verificada_en_mapa' => true,
        'metodo_verificacion' => 'autocomplete',
    ]);

    $producto = crearProductoLogistico([
        'nombre' => 'Mouse inalambrico',
        'numero_parte' => 'LOG-004',
    ]);
    Inventario::query()->create([
        'codigo_producto' => $producto->codigo_producto,
        'clave_proveedor' => null,
        'costo' => 80,
        'precio' => 120,
        'tipo_control' => 'PIEZAS',
        'cantidad_ingresada' => 3,
        'piezas_por_paquete' => null,
        'paquetes_restantes' => 0,
        'piezas_sueltas' => 3,
        'numero_serie' => null,
        'fecha_entrada' => now()->toDateString(),
        'hora_entrada' => now()->format('H:i:s'),
        'forma_ingreso' => 'recibido_almacen',
        'estado_recepcion' => 'recibido',
    ]);
    crearJornadaLogistica();

    $response = $this
        ->actingAs($gerente)
        ->postJson(route('ordenes.store'), [
            'id_cliente' => $cliente->clave_cliente,
            'cliente_direccion_id' => $direccion->id,
            'servicio' => 'Servicio de instalación',
            'tipo_orden' => 'servicio_simple',
            'prioridad' => 'Media',
            'estado' => 'Pendiente',
            'requiere_logistica' => 1,
            'id_tecnico' => $tecnico->id,
            'tecnicos_ids' => [$tecnico->id],
            'tipo_pago' => 'efectivo',
            'precio' => 120,
            'costo_operativo' => 0,
            'descripcion' => 'Servicio sin entrega logística',
            'descripcion_servicio' => 'No debe crear movimiento',
            'moneda' => 'MXN',
            'tasa_cambio' => 1,
            'productos' => [
                [
                    'codigo_producto' => $producto->codigo_producto,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => 1,
                    'precio' => 120,
                ],
            ],
            'precio_escrito' => '',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $orden = OrdenServicio::query()->latest('id_orden_servicio')->firstOrFail();

    expect($orden->tipo_orden)->toBe('servicio_simple')
        ->and(MovimientoLogistico::query()
            ->where('orden_servicio_id', $orden->id_orden_servicio)
            ->count())
        ->toBe(0);
});

function crearClienteLogistico(array $attributes = []): Cliente
{
    static $seq = 1;

    $index = $seq++;

    return Cliente::query()->create(array_merge([
        'codigo_cliente' => 'CLI-LOG-' . $index,
        'nombre' => 'Cliente Log ' . $index,
        'nombre_empresa' => 'Empresa Log ' . $index,
        'direccion_fiscal' => 'Av. Siempre Viva 100',
        'contacto' => 'Contacto Log ' . $index,
        'telefono' => '442900' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'correo_electronico' => 'cliente-log-' . $index . '@example.com',
        'datos_fiscales' => 'RFCLOG' . $index,
        'ubicacion' => 'Querétaro',
    ], $attributes));
}

function crearProductoLogistico(array $attributes = []): Producto
{
    static $seq = 1;

    $index = $seq++;

    return Producto::query()->create(array_merge([
        'nombre' => 'Producto Logistico ' . $index,
        'numero_parte' => 'LOG-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
        'categoria' => 'Logistica',
        'clave_prodserv' => '43222600',
        'unidad' => 'pieza',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para pruebas de logística',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ], $attributes));
}

function crearProveedorLogistico(array $attributes = []): Proveedor
{
    static $seq = 1;

    $index = $seq++;
    $homoclave = strtoupper(str_pad(base_convert($index, 10, 36), 3, '0', STR_PAD_LEFT));

    return Proveedor::query()->create(array_merge([
        'nombre' => 'Proveedor Log ' . $index,
        'rfc' => 'LOGA010101' . $homoclave,
        'alias' => 'Alias Proveedor ' . $index,
        'direccion' => 'Av. Universidad 200, Queretaro, Qro.',
        'direccion_logistica' => 'Av. Universidad 200, Queretaro, Qro.',
        'direccion_logistica_place_id' => 'proveedor-place-' . $index,
        'direccion_logistica_latitud' => 20.59321,
        'direccion_logistica_longitud' => -100.39234,
        'direccion_logistica_referencia' => 'Acceso de carga',
        'direccion_logistica_verificada_en_mapa' => true,
        'direccion_logistica_metodo' => 'autocomplete',
        'contacto' => 'Contacto Proveedor ' . $index,
        'telefono' => '442800' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'correo' => 'proveedor-log-' . $index . '@example.com',
    ], $attributes));
}

function crearJornadaLogistica(array $attributes = []): JornadaLogistica
{
    static $seq = 1;

    $index = $seq++;

    return JornadaLogistica::query()->create(array_merge([
        'folio' => 'JL-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
        'nombre' => 'Ruta logística ' . $index,
        'fecha' => now()->toDateString(),
        'estado' => 'abierta',
        'opened_at' => now(),
    ], $attributes));
}
