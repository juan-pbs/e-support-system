<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsuariosConectadosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'isSystem') || ! $user->isSystem()) {
            abort(403);
        }

        $sessionTable = config('session.table', 'sessions');
        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $onlineWindowMinutes = 5;

        $now = now();
        $validSince = $now->copy()->subMinutes($lifetimeMinutes)->timestamp;
        $onlineSince = $now->copy()->subMinutes($onlineWindowMinutes)->timestamp;

        $usuarios = collect();
        $totalSesionesVigentes = 0;
        $totalUsuariosConectados = 0;

        if (Schema::hasTable($sessionTable)) {
            $sessions = DB::table($sessionTable)
                ->join('users', $sessionTable . '.user_id', '=', 'users.id')
                ->whereNotNull($sessionTable . '.user_id')
                ->where($sessionTable . '.last_activity', '>=', $validSince)
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.puesto',
                    $sessionTable . '.ip_address',
                    $sessionTable . '.user_agent',
                    $sessionTable . '.last_activity',
                ])
                ->orderByDesc($sessionTable . '.last_activity')
                ->get();

            $totalSesionesVigentes = $sessions->count();

            $usuarios = $sessions
                ->groupBy('id')
                ->map(function ($items) use ($onlineSince) {
                    $last = $items->sortByDesc('last_activity')->first();
                    $lastActivity = Carbon::createFromTimestamp((int) $last->last_activity);
                    $isOnline = (int) $last->last_activity >= $onlineSince;

                    return (object) [
                        'id' => $last->id,
                        'name' => $last->name,
                        'email' => $last->email,
                        'puesto' => $last->puesto ?: 'Sin rol',
                        'ip_address' => $last->ip_address ?: '-',
                        'user_agent' => $last->user_agent ?: '-',
                        'last_activity' => $lastActivity,
                        'last_activity_human' => $lastActivity->diffForHumans(),
                        'sessions_count' => $items->count(),
                        'is_online' => $isOnline,
                    ];
                })
                ->sortByDesc('last_activity')
                ->values();

            $totalUsuariosConectados = $usuarios->where('is_online', true)->count();
        }

        return view('sistema.usuarios-conectados.index', [
            'usuarios' => $usuarios,
            'totalUsuarios' => $usuarios->count(),
            'totalUsuariosConectados' => $totalUsuariosConectados,
            'totalSesionesVigentes' => $totalSesionesVigentes,
            'onlineWindowMinutes' => $onlineWindowMinutes,
            'lifetimeMinutes' => $lifetimeMinutes,
            'sessionTableExists' => Schema::hasTable($sessionTable),
        ]);
    }
}
