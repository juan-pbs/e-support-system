<?php

use App\Models\Cliente;
use App\Models\ClienteDireccionLogistica;
use App\Models\GoogleCalendarAccount;
use App\Models\GoogleCalendarOrderEvent;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.google_calendar.client_id', 'test-client-id');
    Config::set('services.google_calendar.client_secret', 'test-client-secret');
    Config::set('services.google_calendar.redirect', 'https://example.com/integraciones/google-calendar/callback');
});

it('guarda la conexion de google calendar despues del callback oauth', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'token-123',
            'refresh_token' => 'refresh-123',
            'expires_in' => 3600,
            'scope' => 'openid email profile https://www.googleapis.com/auth/calendar.events',
            'token_type' => 'Bearer',
        ], 200),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-sub-1',
            'email' => 'tecnico.calendar@example.com',
        ], 200),
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'email' => 'tecnico.local@example.com',
    ]);

    $response = $this
        ->actingAs($tecnico)
        ->withSession([
            'google_calendar.oauth_state' => 'state-123',
            'google_calendar.return_route' => 'tecnico.google-calendar.index',
        ])
        ->get(route('google-calendar.callback', [
            'state' => 'state-123',
            'code' => 'oauth-code-123',
        ]));

    $response
        ->assertRedirect(route('tecnico.google-calendar.index'))
        ->assertSessionHas('success');

    $account = GoogleCalendarAccount::query()->where('user_id', $tecnico->id)->firstOrFail();

    expect($account->google_email)->toBe('tecnico.calendar@example.com')
        ->and($account->refresh_token)->toBe('refresh-123')
        ->and($account->sync_enabled)->toBeTrue()
        ->and($account->is_connected)->toBeTrue();
});

it('sincroniza una orden asignada al google calendar del tecnico conectado', function () {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
            'id' => 'google-event-123',
        ], 200),
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Calendar',
    ]);

    GoogleCalendarAccount::query()->create([
        'user_id' => $tecnico->id,
        'google_email' => 'tecnico.calendar@example.com',
        'calendar_id' => 'primary',
        'access_token' => 'access-token-123',
        'refresh_token' => 'refresh-token-123',
        'token_expires_at' => now()->addHour(),
        'sync_enabled' => true,
        'connected_at' => now(),
    ]);

    $cliente = crearClienteCalendario();
    $direccion = ClienteDireccionLogistica::query()->create([
        'clave_cliente' => $cliente->clave_cliente,
        'alias' => 'Sucursal Centro',
        'direccion_formateada' => 'Av. Tecnologico 100, Querétaro, Qro.',
        'place_id' => 'cliente-calendar-place-1',
        'latitud' => 20.588793,
        'longitud' => -100.389888,
        'referencia' => 'Recepción principal',
        'activa' => true,
        'predeterminada' => true,
        'verificada_en_mapa' => true,
        'metodo_verificacion' => 'autocomplete',
    ]);

    $orden = OrdenServicio::query()->create([
        'id_cliente' => $cliente->clave_cliente,
        'cliente_direccion_id' => $direccion->id,
        'id_tecnico' => $tecnico->id,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'Pendiente',
        'prioridad' => 'Alta',
        'servicio' => 'Instalación de cámaras',
        'descripcion_servicio' => 'Instalación en sucursal',
        'descripcion' => 'Evento de calendario automático',
        'precio' => 0,
        'costo_operativo' => 0,
        'tipo_pago' => 'efectivo',
        'tipo_orden' => 'servicio_simple',
        'moneda' => 'MXN',
        'tasa_cambio' => 1,
        'impuestos' => 0,
        'requiere_logistica' => false,
    ]);
    $orden->tecnicos()->sync([$tecnico->id]);

    /** @var GoogleCalendarService $service */
    $service = app(GoogleCalendarService::class);
    $service->syncOrderAssignments($orden->fresh(['cliente.direccionesLogisticas', 'direccionCliente', 'tecnico', 'tecnicos']));

    $mapping = GoogleCalendarOrderEvent::query()
        ->where('orden_servicio_id', $orden->id_orden_servicio)
        ->where('user_id', $tecnico->id)
        ->firstOrFail();

    expect($mapping->event_id)->toBe('google-event-123');

    Http::assertSent(function ($request) use ($orden) {
        return $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events'
            && $request['summary'] === $orden->folio . ' · Empresa Calendar 1';
    });
});

it('elimina el evento remoto cuando se retira la orden del calendario', function () {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/google-event-123' => Http::response('', 204),
    ]);

    $tecnico = User::factory()->create([
        'puesto' => 'tecnico',
        'name' => 'Tecnico Calendar 2',
    ]);

    $account = GoogleCalendarAccount::query()->create([
        'user_id' => $tecnico->id,
        'google_email' => 'tecnico.calendar2@example.com',
        'calendar_id' => 'primary',
        'access_token' => 'access-token-456',
        'refresh_token' => 'refresh-token-456',
        'token_expires_at' => now()->addHour(),
        'sync_enabled' => true,
        'connected_at' => now(),
    ]);

    $cliente = crearClienteCalendario();
    $orden = OrdenServicio::query()->create([
        'id_cliente' => $cliente->clave_cliente,
        'id_tecnico' => $tecnico->id,
        'fecha_orden' => now()->toDateString(),
        'estado' => 'Pendiente',
        'prioridad' => 'Media',
        'servicio' => 'Servicio preventivo',
        'precio' => 0,
        'costo_operativo' => 0,
        'tipo_pago' => 'efectivo',
        'tipo_orden' => 'servicio_simple',
        'moneda' => 'MXN',
        'tasa_cambio' => 1,
        'impuestos' => 0,
        'requiere_logistica' => false,
    ]);

    $mapping = GoogleCalendarOrderEvent::query()->create([
        'google_calendar_account_id' => $account->id,
        'user_id' => $tecnico->id,
        'orden_servicio_id' => $orden->id_orden_servicio,
        'calendar_id' => 'primary',
        'event_id' => 'google-event-123',
        'payload_hash' => 'abc',
        'synced_at' => now(),
    ]);

    /** @var GoogleCalendarService $service */
    $service = app(GoogleCalendarService::class);
    $service->removeOrderFromCalendars($orden);

    expect(GoogleCalendarOrderEvent::query()->find($mapping->id))->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events/google-event-123');
});

function crearClienteCalendario(array $attributes = []): Cliente
{
    static $seq = 1;

    $index = $seq++;

    return Cliente::query()->create(array_merge([
        'codigo_cliente' => 'CLI-CAL-' . $index,
        'nombre' => 'Cliente Calendar ' . $index,
        'nombre_empresa' => 'Empresa Calendar ' . $index,
        'direccion_fiscal' => 'Av. Universidad 10',
        'contacto' => 'Contacto Calendar ' . $index,
        'telefono' => '442700' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'correo_electronico' => 'cliente-calendar-' . $index . '@example.com',
        'datos_fiscales' => 'RFCCAL' . $index,
        'ubicacion' => 'Querétaro',
    ], $attributes));
}
