<?php

namespace App\Services\GoogleCalendar;

use App\Models\GoogleCalendarAccount;
use App\Models\GoogleCalendarOrderEvent;
use App\Models\OrdenServicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    public const SCOPE_CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

    public function isConfigured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && filled(config('services.google_calendar.redirect'));
    }

    public function authorizationUrl(string $state): string
    {
        $params = [
            'client_id' => (string) config('services.google_calendar.client_id'),
            'redirect_uri' => (string) config('services.google_calendar.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'email',
                'profile',
                self::SCOPE_CALENDAR_EVENTS,
            ]),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => (string) config('services.google_calendar.client_id'),
                'client_secret' => (string) config('services.google_calendar.client_secret'),
                'redirect_uri' => (string) config('services.google_calendar.redirect'),
                'grant_type' => 'authorization_code',
            ]);

        $response->throw();

        return (array) $response->json();
    }

    public function fetchGoogleProfile(string $accessToken): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        $response->throw();

        return (array) $response->json();
    }

    public function storeConnection(User $user, array $tokenPayload, array $profile = []): GoogleCalendarAccount
    {
        $existing = $user->googleCalendarAccount()->first();
        $refreshToken = (string) ($tokenPayload['refresh_token'] ?? '');

        $account = $existing ?: new GoogleCalendarAccount([
            'user_id' => $user->id,
        ]);

        $account->google_email = (string) ($profile['email'] ?? $account->google_email);
        $account->google_sub = (string) ($profile['sub'] ?? $account->google_sub);
        $account->calendar_id = $account->calendar_id ?: 'primary';
        $account->access_token = (string) ($tokenPayload['access_token'] ?? $account->access_token);
        $account->refresh_token = $refreshToken !== ''
            ? $refreshToken
            : ($account->refresh_token ?: null);
        $account->scopes = $this->normalizeScopes($tokenPayload['scope'] ?? $account->scopes);
        $account->token_expires_at = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : $account->token_expires_at;
        $account->sync_enabled = true;
        $account->connected_at = now();
        $account->disconnected_at = null;
        $account->save();

        return $account->fresh();
    }

    public function disconnect(User $user): void
    {
        $account = $user->googleCalendarAccount;
        if (!$account) {
            return;
        }

        try {
            if (!empty($account->access_token)) {
                Http::asForm()
                    ->timeout(15)
                    ->post('https://oauth2.googleapis.com/revoke', [
                        'token' => $account->access_token,
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo revocar el token de Google Calendar.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $account->access_token = null;
        $account->refresh_token = null;
        $account->token_expires_at = null;
        $account->sync_enabled = false;
        $account->disconnected_at = now();
        $account->save();
    }

    public function setSyncEnabled(User $user, bool $enabled): void
    {
        $account = $user->googleCalendarAccount;
        if (!$account) {
            return;
        }

        $account->sync_enabled = $enabled;
        $account->save();
    }

    public function syncAssignedOrdersForUser(User $user): int
    {
        $account = $user->googleCalendarAccount;
        if (!$account || !$account->is_connected || !$account->sync_enabled || !$this->isConfigured()) {
            return 0;
        }

        $orders = OrdenServicio::query()
            ->with(['cliente.direccionesLogisticas', 'direccionCliente', 'tecnico', 'tecnicos'])
            ->where(function ($query) use ($user) {
                $query->where('id_tecnico', $user->id)
                    ->orWhereHas('tecnicos', fn ($q) => $q->where('users.id', $user->id));
            })
            ->get();

        foreach ($orders as $order) {
            $this->syncOrderAssignments($order);
        }

        return $orders->count();
    }

    public function syncOrderAssignments(OrdenServicio $orden): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $orden->loadMissing(['cliente.direccionesLogisticas', 'direccionCliente', 'tecnico', 'tecnicos']);

        $assignedUsers = $this->assignedUsersForOrder($orden)->keyBy('id');
        $existingEvents = GoogleCalendarOrderEvent::query()
            ->with('account')
            ->where('orden_servicio_id', $orden->getKey())
            ->get();

        foreach ($existingEvents as $existingEvent) {
            $shouldKeep = $assignedUsers->has((int) $existingEvent->user_id)
                && $existingEvent->account?->is_connected
                && $existingEvent->account?->sync_enabled;

            if (!$shouldKeep) {
                $this->deleteCalendarEventMapping($existingEvent);
            }
        }

        foreach ($assignedUsers as $assignedUser) {
            $account = $assignedUser->googleCalendarAccount;
            if (!$account || !$account->is_connected || !$account->sync_enabled) {
                continue;
            }

            $this->upsertCalendarEvent($orden, $assignedUser, $account);
        }
    }

    public function removeOrderFromCalendars(OrdenServicio $orden): void
    {
        $events = GoogleCalendarOrderEvent::query()
            ->with('account')
            ->where('orden_servicio_id', $orden->getKey())
            ->get();

        foreach ($events as $event) {
            $this->deleteCalendarEventMapping($event);
        }
    }

    protected function upsertCalendarEvent(OrdenServicio $orden, User $user, GoogleCalendarAccount $account): void
    {
        $mapping = GoogleCalendarOrderEvent::query()
            ->where('google_calendar_account_id', $account->id)
            ->where('orden_servicio_id', $orden->getKey())
            ->first();

        $payload = $this->buildOrderEventPayload($orden, $user);
        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($mapping && $mapping->payload_hash === $payloadHash) {
            $mapping->synced_at = now();
            $mapping->save();
            return;
        }

        $calendarId = $account->calendar_id ?: 'primary';

        if ($mapping?->event_id) {
            $response = $this->authorizedRequest($account)
                ->put($this->calendarEventEndpoint($calendarId, $mapping->event_id), $payload);

            if ($response->status() === 404) {
                $mapping->delete();
                $mapping = null;
            } else {
                $response->throw();
            }
        }

        if (!$mapping) {
            $response = $this->authorizedRequest($account)
                ->post($this->calendarEventsEndpoint($calendarId), $payload);

            $response->throw();
            $eventData = (array) $response->json();

            GoogleCalendarOrderEvent::query()->updateOrCreate(
                [
                    'google_calendar_account_id' => $account->id,
                    'orden_servicio_id' => $orden->getKey(),
                ],
                [
                    'user_id' => $user->id,
                    'calendar_id' => $calendarId,
                    'event_id' => (string) ($eventData['id'] ?? ''),
                    'payload_hash' => $payloadHash,
                    'synced_at' => now(),
                ]
            );

            return;
        }

        $mapping->user_id = $user->id;
        $mapping->calendar_id = $calendarId;
        $mapping->payload_hash = $payloadHash;
        $mapping->synced_at = now();
        $mapping->save();
    }

    protected function deleteCalendarEventMapping(GoogleCalendarOrderEvent $mapping): void
    {
        $account = $mapping->account;

        if ($account && $account->is_connected && !empty($mapping->event_id)) {
            try {
                $response = $this->authorizedRequest($account)
                    ->delete($this->calendarEventEndpoint($mapping->calendar_id ?: 'primary', $mapping->event_id));

                if (!in_array($response->status(), [200, 204, 404], true)) {
                    $response->throw();
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo eliminar el evento de Google Calendar.', [
                    'mapping_id' => $mapping->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $mapping->delete();
    }

    protected function buildOrderEventPayload(OrdenServicio $orden, User $user): array
    {
        $eventDate = $orden->fecha_orden
            ? Carbon::parse($orden->fecha_orden)
            : now();

        $endDate = $eventDate->copy()->addDay();
        $cliente = $orden->cliente;
        $direccion = $orden->direccionCliente?->direccion_formateada
            ?: $cliente?->ubicacion
            ?: $cliente?->direccion_fiscal;

        $clienteNombre = trim((string) ($cliente?->nombre_empresa ?: $cliente?->nombre ?: 'Cliente'));
        $servicio = trim((string) ($orden->servicio ?: $orden->descripcion_servicio ?: 'Orden de servicio'));
        $tecnicos = trim((string) ($orden->tecnicos_nombres ?: optional($orden->tecnico)->name ?: $user->name));
        $folio = $orden->folio;

        $descriptionBlocks = array_filter([
            "Orden: {$folio}",
            "Cliente: {$clienteNombre}",
            "Servicio: {$servicio}",
            $direccion ? "Dirección: {$direccion}" : null,
            $orden->estado ? "Estado: {$orden->estado}" : null,
            $orden->prioridad ? "Prioridad: {$orden->prioridad}" : null,
            $tecnicos ? "Técnicos: {$tecnicos}" : null,
            $orden->descripcion ? "Descripción: {$orden->descripcion}" : null,
            $orden->condiciones_generales ? "Notas: {$orden->condiciones_generales}" : null,
            route('tecnico.detalles', ['orden' => $orden->getKey()]),
        ]);

        return [
            'summary' => "{$folio} · {$clienteNombre}",
            'description' => implode("\n\n", $descriptionBlocks),
            'location' => $direccion,
            'colorId' => $this->googleColorIdForOrder($orden),
            'start' => [
                'date' => $eventDate->toDateString(),
                'timeZone' => config('app.timezone', 'America/Mexico_City'),
            ],
            'end' => [
                'date' => $endDate->toDateString(),
                'timeZone' => config('app.timezone', 'America/Mexico_City'),
            ],
            'extendedProperties' => [
                'private' => [
                    'app' => 'e-support-system',
                    'order_id' => (string) $orden->getKey(),
                    'order_folio' => $folio,
                    'assigned_user_id' => (string) $user->id,
                ],
            ],
        ];
    }

    protected function googleColorIdForOrder(OrdenServicio $orden): string
    {
        $estado = Str::lower(trim((string) $orden->estado));
        $prioridad = Str::lower(trim((string) $orden->prioridad));

        if (str_contains($estado, 'cancel')) {
            return '11';
        }

        if (str_contains($estado, 'final') || str_contains($estado, 'complet')) {
            return '10';
        }

        return match ($prioridad) {
            'urgente' => '11',
            'alta' => '6',
            'media' => '5',
            'baja' => '2',
            default => '1',
        };
    }

    protected function assignedUsersForOrder(OrdenServicio $orden): Collection
    {
        $users = collect();

        if ($orden->relationLoaded('tecnicos')) {
            $users = $users->merge($orden->tecnicos);
        } else {
            $users = $users->merge($orden->tecnicos()->get());
        }

        if (!empty($orden->id_tecnico)) {
            $primaryTech = $orden->relationLoaded('tecnico')
                ? $orden->tecnico
                : User::query()->find($orden->id_tecnico);

            if ($primaryTech) {
                $users->push($primaryTech);
            }
        }

        return $users
            ->filter()
            ->unique('id')
            ->values();
    }

    protected function authorizedRequest(GoogleCalendarAccount $account)
    {
        $token = $this->validAccessToken($account);

        return Http::acceptJson()
            ->timeout(20)
            ->withToken($token)
            ->withHeaders([
                'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.8',
            ]);
    }

    protected function validAccessToken(GoogleCalendarAccount $account): string
    {
        if (
            !empty($account->access_token)
            && $account->token_expires_at
            && $account->token_expires_at->gt(now()->addMinutes(2))
        ) {
            return (string) $account->access_token;
        }

        if (empty($account->refresh_token)) {
            return (string) $account->access_token;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('services.google_calendar.client_id'),
                'client_secret' => (string) config('services.google_calendar.client_secret'),
                'refresh_token' => (string) $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        $response->throw();
        $payload = (array) $response->json();

        $account->access_token = (string) ($payload['access_token'] ?? $account->access_token);
        $account->token_expires_at = isset($payload['expires_in'])
            ? now()->addSeconds((int) $payload['expires_in'])
            : $account->token_expires_at;
        $account->disconnected_at = null;
        $account->save();

        return (string) $account->access_token;
    }

    protected function calendarEventsEndpoint(string $calendarId): string
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events';
    }

    protected function calendarEventEndpoint(string $calendarId, string $eventId): string
    {
        return $this->calendarEventsEndpoint($calendarId) . '/' . rawurlencode($eventId);
    }

    protected function normalizeScopes(mixed $scopes): array
    {
        if (is_array($scopes)) {
            return array_values(array_filter(array_map('strval', $scopes)));
        }

        if (is_string($scopes)) {
            return array_values(array_filter(explode(' ', trim($scopes))));
        }

        return [];
    }
}
