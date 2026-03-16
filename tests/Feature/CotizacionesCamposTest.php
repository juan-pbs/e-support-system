<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\User;

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
        ]);

    $response->assertRedirect(route('cotizaciones.vista'));

    $cotizacion = Cotizacion::latest('id_cotizacion')->first();

    expect($cotizacion)->not->toBeNull()
        ->and($cotizacion->condiciones_pago)->toBe('efectivo')
        ->and($cotizacion->tiempo_entrega)->toBe('3 dias habiles')
        ->and($cotizacion->cantidad_escrita)->toBe('CIENTO CINCUENTA PESOS 00/100 M.N.');
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
