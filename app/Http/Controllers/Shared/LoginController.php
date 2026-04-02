<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index(): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $puesto = strtolower(trim((string) (Auth::user()->puesto ?? '')));

        return match ($puesto) {
            'gerente' => redirect('/gerente'),
            'tecnico' => redirect('/tecnico'),
            'admin' => redirect('/admin'),
            default => $this->logout(),
        };
    }

    private function logout(): RedirectResponse
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'Tu sesión se reinició. Inicia sesión nuevamente.');
    }
}
