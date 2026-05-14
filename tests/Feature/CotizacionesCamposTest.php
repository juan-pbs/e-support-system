<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('guarda condiciones de pago, tiempo de entrega y cantidad con letra editable en cotizaciones', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion();

    $response = $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.guardar'), [
            'tipo_solicitud' => 'servicio',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 0,
            'precio_servicio' => 150,
            'descripcion' => 'Cotizacion de prueba',
            'descripcion_servicio' => 'Servicio tecnico',
            'productos_json' => '[]',
            'condiciones_pago' => 'efectivo',
            'tiempo_entrega' => '3 dias habiles',
            'cantidad_escrita' => 'CIENTO CINCUENTA PESOS 00/100 M.N.',
            'observaciones_pdf' => 'Observacion de cierre para PDF.',
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::latest('id_cotizacion')->first();

    expect($cotizacion)->not->toBeNull()
        ->and($cotizacion->condiciones_pago)->toBe('efectivo')
        ->and($cotizacion->tiempo_entrega)->toBe('3 dias habiles')
        ->and($cotizacion->cantidad_escrita)->toBe('CIENTO CINCUENTA PESOS 00/100 M.N.')
        ->and($cotizacion->observaciones_pdf)->toBe('Observacion de cierre para PDF.');
});

it('genera cantidad con letra automaticamente cuando no se captura manualmente', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-2',
        'correo_electronico' => 'cotizacion2@example.com',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.guardar'), [
            'tipo_solicitud' => 'servicio',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 25,
            'precio_servicio' => 100,
            'descripcion' => 'Cotizacion autogenerada',
            'descripcion_servicio' => 'Visita tecnica',
            'productos_json' => '[]',
            'condiciones_pago' => 'credito_cliente',
            'tiempo_entrega' => '24 horas',
            'cantidad_escrita' => '',
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::latest('id_cotizacion')->first();

    expect($cotizacion)->not->toBeNull()
        ->and($cotizacion->cantidad_escrita)->toContain('PESOS')
        ->and($cotizacion->cantidad_escrita)->toContain('M.N.');
});

it('permite guardar y descargar la cotizacion en una sola accion', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-DL',
        'correo_electronico' => 'cotizacion-descarga@example.com',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.guardar'), [
            'accion' => 'guardar_descargar',
            'tipo_solicitud' => 'servicio',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 0,
            'precio_servicio' => 250,
            'descripcion' => 'Cotizacion con descarga inmediata',
            'descripcion_servicio' => 'Servicio con PDF',
            'productos_json' => '[]',
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::latest('id_cotizacion')->first();

    $response->assertSessionHas('download_pdf_url', route('cotizaciones.descargarPDF', $cotizacion->id_cotizacion));
});

it('permite actualizar y descargar la cotizacion redirigiendo al indice', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-UPD',
        'correo_electronico' => 'cotizacion-update@example.com',
    ]);

    $cotizacion = Cotizacion::query()->create([
        'fecha' => now(),
        'vigencia' => now()->addDays(5),
        'moneda' => 'MXN',
        'tipo_solicitud' => 'servicio',
        'registro_cliente' => $cliente->clave_cliente,
        'descripcion' => 'Cotizacion base',
        'costo_operativo' => 0,
        'iva' => 0,
        'total' => 100,
        'cantidad_escrita' => 'CIEN PESOS 00/100 M.N.',
        'condiciones_pago' => 'efectivo',
        'tiempo_entrega' => 'Inmediato',
        'edit_count' => 0,
        'process_count' => 0,
        'estado_cotizacion' => 'borrador',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->put(route('cotizaciones.actualizar', $cotizacion->id_cotizacion), [
            'accion' => 'guardar_descargar',
            'tipo_solicitud' => 'servicio',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 0,
            'precio_servicio' => 350,
            'descripcion' => 'Cotizacion actualizada con descarga',
            'descripcion_servicio' => 'Servicio actualizado',
            'productos_json' => '[]',
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));
    $response->assertSessionHas('download_pdf_url', route('cotizaciones.descargarPDF', $cotizacion->id_cotizacion));
});

it('guarda observaciones por producto dentro del detalle de cotizacion', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-3',
        'correo_electronico' => 'cotizacion3@example.com',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.guardar'), [
            'tipo_solicitud' => 'venta',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 0,
            'descripcion' => 'Cotizacion con observaciones',
            'productos_json' => json_encode([
                [
                    'id' => 'custom-1',
                    'name' => 'Gabinete rack',
                    'quantity' => 2,
                    'price' => 1500,
                    'unit' => 'PZA',
                    'description' => "Incluye instalacion\nY pruebas finales",
                ],
            ], JSON_UNESCAPED_UNICODE),
            'condiciones_pago' => 'transferencia',
            'tiempo_entrega' => '5 dias habiles',
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::latest('id_cotizacion')->first();
    $detalle = DetalleCotizacionProducto::query()
        ->where('id_cotizacion', $cotizacion->id_cotizacion)
        ->first();

    expect($detalle)->not->toBeNull()
        ->and($detalle->descripcion_item)->toBe("Incluye instalacion\nY pruebas finales")
        ->and($detalle->cantidad)->toBe(2)
        ->and($detalle->total)->toBe(3000.0);
});

it('muestra la busqueda por numero de parte en crear y editar cotizaciones', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-4',
        'correo_electronico' => 'cotizacion4@example.com',
    ]);

    $producto = \App\Models\Producto::query()->create([
        'nombre' => 'Switch administrable',
        'numero_parte' => 'SW-24P-G2',
        'categoria' => 'Redes',
        'clave_prodserv' => '43222612',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Switch gigabit',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $cotizacion = Cotizacion::query()->create([
        'fecha' => now(),
        'vigencia' => now()->addDays(5),
        'moneda' => 'MXN',
        'tipo_solicitud' => 'venta',
        'registro_cliente' => $cliente->clave_cliente,
        'descripcion' => 'Cotizacion editable',
        'costo_operativo' => 0,
        'iva' => 0,
        'total' => 1000,
        'cantidad_escrita' => 'MIL PESOS 00/100 M.N.',
        'condiciones_pago' => 'efectivo',
        'tiempo_entrega' => 'Inmediato',
        'edit_count' => 0,
        'process_count' => 0,
        'estado_cotizacion' => 'borrador',
    ]);

    DetalleCotizacionProducto::query()->create([
        'id_cotizacion' => $cotizacion->id_cotizacion,
        'codigo_producto' => $producto->codigo_producto,
        'nombre_producto' => $producto->nombre,
        'descripcion_item' => 'Observacion inicial',
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'total' => 1000,
        'unidad' => 'PZA',
    ]);

    $crear = $this
        ->actingAs($gerente)
        ->get(route('cotizaciones.crear'));

    $crear->assertOk()
        ->assertSee('Buscar productos o número de parte')
        ->assertSee('Observaciones');

    $editar = $this
        ->actingAs($gerente)
        ->get(route('cotizaciones.editar', $cotizacion->id_cotizacion));

    $editar->assertOk()
        ->assertSee('Buscar productos o número de parte')
        ->assertSee('Observaciones')
        ->assertSee('SW-24P-G2');
});

it('forma el folio de cotizacion con codigo de cliente y numero de cotizacion', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-QRO-99',
        'correo_electronico' => 'cotizacion5@example.com',
    ]);

    $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.guardar'), [
            'tipo_solicitud' => 'servicio',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'costo_operativo' => 0,
            'precio_servicio' => 250,
            'descripcion' => 'Cotizacion con nuevo folio',
            'descripcion_servicio' => 'Servicio de configuracion',
            'productos_json' => '[]',
        ])
        ->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::with('cliente')->latest('id_cotizacion')->first();

    expect($cotizacion)->not->toBeNull()
        ->and($cotizacion->folio)->toBe('CLI-QRO-99 ' . $cotizacion->id_cotizacion);

    $autocomplete = $this
        ->actingAs($gerente)
        ->getJson(route('cotizaciones.autocomplete', ['term' => 'CLI-QRO-99']));

    $autocomplete->assertOk()
        ->assertJsonFragment([
            'value' => 'CLI-QRO-99 ' . $cotizacion->id_cotizacion,
        ]);
});

it('genera la vista previa pdf de cotizacion para una sesion autenticada', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-PREVIEW',
        'correo_electronico' => 'cotizacion-preview@example.com',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('cotizaciones.preview'), [
            'tipo_solicitud' => 'venta',
            'moneda' => 'MXN',
            'cliente_id' => $cliente->clave_cliente,
            'vigencia' => now()->addDays(7)->format('Y-m-d'),
            'descripcion' => 'Vista previa de cotizacion',
            'productos_json' => json_encode([
                [
                    'id' => 'custom-preview-1',
                    'name' => 'Cable Manhattan',
                    'quantity' => 1,
                    'price' => 548.07,
                    'unit' => 'PZA',
                    'description' => "Extension Activa USB-A 3.0\nVelocidad 5m Color Negro",
                ],
            ], JSON_UNESCAPED_UNICODE),
            'condiciones_pago' => 'efectivo',
            'tiempo_entrega' => '24 horas',
            'cantidad_escrita' => 'SEISCIENTOS TREINTA Y CINCO PESOS 76/100 M.N.',
        ]);

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
});

it('renderiza la tabla del pdf de cotizacion con columnas fijas para descripcion amplia', function () {
    $cliente = crearClienteCotizacion([
        'codigo_cliente' => 'CLI-COT-HTML',
        'correo_electronico' => 'cotizacion-html@example.com',
    ]);

    $cotizacion = (object) [
        'id_cotizacion' => 999,
        'fecha' => now(),
        'created_at' => now(),
        'vigencia' => now()->addDays(7),
        'moneda' => 'MXN',
        'descripcion' => 'Cotizacion html',
        'costo_operativo' => 0,
        'iva' => 87.69,
        'total' => 635.76,
        'cantidad_escrita' => 'SEISCIENTOS TREINTA Y CINCO PESOS 76/100 M.N.',
        'condiciones_pago' => 'efectivo',
        'tiempo_entrega' => '24 horas',
        'observaciones_pdf' => null,
        'nota_fija' => 'PRECIOS SUJETOS A CAMBIO SIN PREVIO AVISO',
        'tasa_cambio' => null,
        'tipo_cambio' => null,
    ];

    $productos = collect([
        (object) [
            'nombre_producto' => 'Cable Manhattan',
            'descripcion_item' => "Extension Activa USB-A 3.0\nVelocidad 5m Color Negro",
            'cantidad' => 1,
            'precio_unitario' => 548.07,
            'total' => 548.07,
        ],
    ]);

    $html = view('pdf.cotizacion', [
        'cotizacion' => $cotizacion,
        'cliente' => $cliente,
        'productos' => $productos,
        'servicio' => null,
        'firma' => [],
        'tasaCambio' => null,
    ])->render();

    expect($html)
        ->toContain('class="descripcion-col"')
        ->toContain('<col style="width: 11%;">')
        ->toContain('<col style="width: 57%;">')
        ->toContain('<col style="width: 16%;">');
});

it('incluye la migracion que amplia la precision del iva de cotizaciones', function () {
    $migration = '2026_05_13_120000_expand_iva_precision_on_cotizaciones_table';
    $migrationPath = database_path("migrations/{$migration}.php");

    expect(DB::table('migrations')->where('migration', $migration)->exists())->toBeTrue()
        ->and($migrationPath)->toBeFile()
        ->and(file_get_contents($migrationPath))->toContain("\$table->decimal('iva', 10, 2)->default(0)->change();");
});

function crearClienteCotizacion(array $attributes = []): Cliente
{
    return Cliente::create(array_merge([
        'codigo_cliente' => 'CLI-COT-1',
        'nombre' => 'Cliente Cotizacion',
        'nombre_empresa' => 'Empresa Cotizacion',
        'direccion_fiscal' => 'Calle Cotizacion 123',
        'contacto' => 'Contacto Cotizacion',
        'telefono' => '5551234567',
        'contacto_adicional' => null,
        'correo_electronico' => 'cotizacion@example.com',
        'datos_fiscales' => 'RFC-COT',
        'ubicacion' => 'Queretaro',
    ], $attributes));
}
