<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $googleCalendar) {}

    public function gerenteIndex(Request $request)
    {
        return $this->renderDashboard($request, 'layouts.sidebar-navigation', true);
    }

    public function tecnicoIndex(Request $request)
    {
        return $this->renderDashboard($request, 'layouts.sidebar-navigation-tecnico', false);
    }

    public function redirect(Request $request)
    {
        abort_unless($this->googleCalendar->isConfigured(), 500, 'Google Calendar no está configurado en este entorno.');

        $state = Str::random(40);

        $request->session()->put('google_calendar.oauth_state', $state);
        $request->session()->put('google_calendar.return_route', $this->returnRouteFor($request->user()));

        return redirect()->away($this->googleCalendar->authorizationUrl($state));
    }

    public function callback(Request $request)
    {
        abort_unless($request->user(), 403);

        $expectedState = (string) $request->session()->pull('google_calendar.oauth_state', '');
        $returnRoute = (string) $request->session()->pull('google_calendar.return_route', $this->returnRouteFor($request->user()));
        $actualState = (string) $request->query('state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            return redirect()->route($returnRoute)
                ->with('error', 'La validación de seguridad con Google Calendar no fue válida. Intenta conectar nuevamente.');
        }

        if ($request->filled('error')) {
            return redirect()->route($returnRoute)
                ->with('error', 'Google Calendar devolvió el error: ' . $request->query('error'));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route($returnRoute)
                ->with('error', 'Google Calendar no devolvió un código de autorización válido.');
        }

        try {
            $tokens = $this->googleCalendar->exchangeAuthorizationCode($code);
            $profile = [];

            if (!empty($tokens['access_token'])) {
                $profile = $this->googleCalendar->fetchGoogleProfile((string) $tokens['access_token']);
            }

            $this->googleCalendar->storeConnection($request->user(), $tokens, $profile);
            $synced = $this->googleCalendar->syncAssignedOrdersForUser($request->user());

            return redirect()->route($returnRoute)
                ->with('success', "Google Calendar quedó conectado. Se sincronizaron {$synced} órdenes asignadas.");
        } catch (\Throwable $e) {
            return redirect()->route($returnRoute)
                ->with('error', 'No se pudo completar la conexión con Google Calendar: ' . $e->getMessage());
        }
    }

    public function disconnect(Request $request)
    {
        $this->googleCalendar->disconnect($request->user());

        return back()->with('success', 'La conexión con Google Calendar se eliminó correctamente.');
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'sync_enabled' => ['required', 'boolean'],
        ]);

        $this->googleCalendar->setSyncEnabled($request->user(), (bool) $data['sync_enabled']);

        return back()->with('success', 'La sincronización automática de Google Calendar se actualizó correctamente.');
    }

    public function syncNow(Request $request)
    {
        $user = $request->user();
        $syncTechnicians = ! $user->hasRole('tecnico');

        $synced = $syncTechnicians
            ? $this->googleCalendar->syncConnectedTechnicians()
            : $this->googleCalendar->syncAssignedOrdersForUser($user);

        if ($syncTechnicians) {
            return back()->with('success', "Se revisaron {$synced} ordenes de los tecnicos conectados para sincronizarlas con Google Calendar.");
        }

        return back()->with('success', "Se revisaron {$synced} órdenes asignadas para sincronizarlas con Google Calendar.");
    }

    protected function renderDashboard(Request $request, string $layout, bool $showTechStatuses)
    {
        $user = $request->user()->load('googleCalendarAccount');
        $account = $user->googleCalendarAccount;
        $techStatuses = collect();

        if ($showTechStatuses) {
            $techStatuses = User::query()
                ->where('puesto', 'tecnico')
                ->with(['googleCalendarAccount', 'ordenesAsignadas'])
                ->orderBy('name')
                ->get()
                ->map(function (User $tecnico) {
                    $account = $tecnico->googleCalendarAccount;

                    return [
                        'id' => $tecnico->id,
                        'name' => $tecnico->name,
                        'email' => $tecnico->email,
                        'calendar_email' => $account?->google_email ?: $tecnico->email,
                        'connected' => (bool) ($account?->is_connected),
                        'sync_enabled' => (bool) ($account?->sync_enabled),
                        'assigned_orders' => $tecnico->ordenesAsignadas->count(),
                        'connected_at' => $account?->connected_at,
                    ];
                });
        }

        return view('shared.google-calendar.index', [
            'layout' => $layout,
            'account' => $account,
            'employeeEmail' => $user->email,
            'googleCalendarConfigured' => $this->googleCalendar->isConfigured(),
            'showTechStatuses' => $showTechStatuses,
            'techStatuses' => $techStatuses,
        ]);
    }

    protected function returnRouteFor(User $user): string
    {
        return $user->hasRole('tecnico')
            ? 'tecnico.google-calendar.index'
            : 'gerente.google-calendar.index';
    }
}
