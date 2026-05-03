<?php

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\User;

test('system user is redirected to gerente dashboard and can access all role modules', function () {
    $system = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $this->actingAs($system)
        ->get(route('dashboard'))
        ->assertRedirect(route('gerente.inicio', absolute: false));

    $this->actingAs($system)->get(route('gerente.inicio'))->assertOk();
    $this->actingAs($system)->get(route('admin.inicio'))->assertOk();
    $this->actingAs($system)->get(route('tecnico.inicio'))->assertOk();
});

test('system user can create another system user from employee module', function () {
    $system = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $response = $this->actingAs($system)->post(route('empleados.store'), [
        'name' => 'Monitor Global',
        'email' => 'sistema.monitor@example.com',
        'password' => 'password',
        'puesto' => 'sistema',
        'contacto' => '5551234567',
        'auth_password' => 'password',
    ]);

    $response->assertRedirect(route('empleados.index', absolute: false));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'email' => 'sistema.monitor@example.com',
        'puesto' => 'sistema',
    ]);
});

test('system user can see all technical services, not only assigned ones', function () {
    $system = User::factory()->create([
        'puesto' => 'sistema',
    ]);
    $tecnicoUno = User::factory()->create([
        'puesto' => 'tecnico',
    ]);
    $tecnicoDos = User::factory()->create([
        'puesto' => 'tecnico',
    ]);

    $cliente = Cliente::create([
        'codigo_cliente' => 'CLI-SYS-001',
        'nombre' => 'Cliente Supervisor',
        'direccion_fiscal' => 'Calle Central 123',
        'telefono' => '5550001111',
        'correo_electronico' => 'cliente.supervisor@example.com',
    ]);

    $ordenUno = OrdenServicio::create([
        'id_cliente' => $cliente->clave_cliente,
        'id_tecnico' => $tecnicoUno->id,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'Pendiente',
        'servicio' => 'Instalacion principal',
    ]);

    $ordenDos = OrdenServicio::create([
        'id_cliente' => $cliente->clave_cliente,
        'id_tecnico' => $tecnicoDos->id,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'Pendiente',
        'servicio' => 'Revision secundaria',
    ]);

    $this->actingAs($tecnicoUno)
        ->get(route('tecnico.servicios'))
        ->assertOk()
        ->assertViewHas('servicios', function ($servicios) use ($ordenUno, $ordenDos) {
            $ids = collect($servicios->items())->pluck('id_orden_servicio')->all();

            return in_array($ordenUno->id_orden_servicio, $ids, true)
                && ! in_array($ordenDos->id_orden_servicio, $ids, true);
        });

    $this->actingAs($system)
        ->get(route('tecnico.servicios'))
        ->assertOk()
        ->assertViewHas('servicios', function ($servicios) use ($ordenUno, $ordenDos) {
            $ids = collect($servicios->items())->pluck('id_orden_servicio')->all();

            return in_array($ordenUno->id_orden_servicio, $ids, true)
                && in_array($ordenDos->id_orden_servicio, $ids, true);
        });
});
