<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{

    public function index()
    {
        if (Auth::check() && Auth::user()->puesto == 'gerente'){
            return redirect('/gerente');
        } elseif (Auth::check() && Auth::user()->puesto == 'tecnico') {
            return redirect('/tecnico');
        } elseif (Auth::check() && Auth::user()->puesto == 'admin') {
            return redirect('/admin');
        } else
        {
            $this->logout();
        }
    }

    private function logout(){
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('/');
    }

}
