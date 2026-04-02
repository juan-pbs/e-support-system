<?php

namespace App\Http\Controllers\Gerencia\Empleados;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class EmpleadoController extends Controller
{
    /** ===== Helpers de rol / contadores ===== */
    private function contarPorRol(string $rol): int
    {
        return User::where('puesto', $rol)->count();
    }

    private function esUltimoDeRol(int $id, string $rol): bool
    {
        return $this->contarPorRol($rol) === 1
            && User::where('id', $id)->where('puesto', $rol)->exists();
    }

    private function puedeAsignarGerente(): bool
    {
        return Auth::check() && Auth::user()->puesto === 'gerente';
    }

    private function rolRank(?string $rol): int
    {
        return match ($rol) {
            'tecnico' => 1,
            'admin'   => 2,
            'gerente' => 3,
            default   => 0,
        };
    }

    private function esBajadaDeRol(?string $from, ?string $to): bool
    {
        return $this->rolRank($to) < $this->rolRank($from);
    }

    private function exigirAuthPassword(Request $request): bool
    {
        $pwd = (string) $request->input('auth_password', '');
        return $pwd !== '' && Hash::check($pwd, Auth::user()->password);
    }

    /** ===== Listado + búsqueda (FIX: cuando viene "Nombre (correo)") ===== */
    public function index(Request $request)
    {
        $busquedaRaw = trim((string) $request->input('busqueda', ''));
        $me = Auth::user();

        // ✅ Si viene del sugerido: "Nombre (correo@x.com)"
        $busquedaNombre = $busquedaRaw;
        $busquedaEmail  = null;

        if ($busquedaRaw !== '' && Str::contains($busquedaRaw, '(') && Str::contains($busquedaRaw, ')')) {
            $posIni = mb_strrpos($busquedaRaw, '(');
            $posFin = mb_strrpos($busquedaRaw, ')');

            if ($posIni !== false && $posFin !== false && $posFin > $posIni) {
                $inside   = trim(mb_substr($busquedaRaw, $posIni + 1, $posFin - $posIni - 1));
                $namePart = trim(mb_substr($busquedaRaw, 0, $posIni));

                if ($inside !== '' && filter_var($inside, FILTER_VALIDATE_EMAIL)) {
                    $busquedaEmail  = mb_strtolower($inside);
                    $busquedaNombre = $namePart !== '' ? $namePart : $busquedaRaw;
                }
            }
        }

        $empleados = User::query()
            ->when($busquedaRaw !== '', function ($q) use ($busquedaRaw, $busquedaNombre, $busquedaEmail) {
                $likeRaw = "%{$busquedaRaw}%";
                $likeNom = "%{$busquedaNombre}%";

                $q->where(function ($w) use ($likeRaw, $likeNom, $busquedaEmail) {
                    // ✅ Búsqueda normal
                    $w->where('name', 'like', $likeRaw)
                      ->orWhere('email', 'like', $likeRaw);

                    // ✅ Si venía "Nombre (correo)", también busca por el nombre limpio
                    $w->orWhere('name', 'like', $likeNom);

                    // ✅ y por el correo si existe
                    if ($busquedaEmail) {
                        $w->orWhere('email', $busquedaEmail)
                          ->orWhere('email', 'like', "%{$busquedaEmail}%");
                    }
                });
            })
            // ✅ Regla de visibilidad para ADMIN
            ->when($me && $me->puesto === 'admin', function ($q) use ($me) {
                $q->where('id', '<>', $me->id)
                  ->where('puesto', '<>', 'gerente');
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // ✅ Mantener variable para la vista (como la estabas usando)
        $busqueda = $busquedaRaw;

        return view('gerencia.empleados.index', compact('empleados', 'busqueda'));
    }

    /** ===== Formulario de alta ===== */
    public function create()
    {
        return view('gerencia.empleados.create');
    }

    /** ===== Guardar empleado (pide auth_password siempre) ===== */
    public function store(Request $request)
    {
        $request->merge([
            'name'     => trim((string) $request->name),
            'email'    => $request->filled('email') ? mb_strtolower(trim($request->email)) : null,
            'contacto' => $request->filled('contacto') ? preg_replace('/\D+/', '', $request->contacto) : null,
            'puesto'   => $request->filled('puesto') ? mb_strtolower(trim($request->puesto)) : null,
        ]);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'puesto'   => ['required', Rule::in(['gerente','admin','tecnico'])],
            'contacto' => 'nullable|string|max:20',
        ]);

        if ($request->puesto === 'gerente' && ! $this->puedeAsignarGerente()) {
            return back()->withInput()->with('error', 'No tienes permiso para asignar el rol GERENTE.');
        }

        if (Auth::user()->puesto === 'admin' && $request->puesto !== 'tecnico') {
            return back()->withInput()->with('error', 'Un ADMIN solo puede crear usuarios con rol TÉCNICO.');
        }

        if (! $this->exigirAuthPassword($request)) {
            return back()->withInput()->with('error', 'Contraseña de autorización incorrecta.');
        }

        User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'puesto'            => $request->puesto,
            'contacto'          => $request->contacto,
            'remember_token'    => Str::random(60),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('empleados.index')->with('success', 'Empleado registrado correctamente.');
    }

    /** ===== Formulario de edición ===== */
    public function edit($id)
    {
        $empleado = User::findOrFail($id);
        return view('gerencia.empleados.edit', compact('empleado'));
    }

    /** ===== Actualizar empleado ===== */
    public function update(Request $request, $id)
    {
        $empleado = User::findOrFail($id);

        $request->merge([
            'name'     => trim((string) $request->name),
            'email'    => $request->filled('email') ? mb_strtolower(trim($request->email)) : null,
            'contacto' => $request->filled('contacto') ? preg_replace('/\D+/', '', $request->contacto) : null,
            'puesto'   => $request->filled('puesto') ? mb_strtolower(trim($request->puesto)) : null,
        ]);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required','email', Rule::unique('users','email')->ignore($empleado->id)],
            'puesto'   => ['required', Rule::in(['gerente','admin','tecnico'])],
            'contacto' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $isSelf         = Auth::id() === $empleado->id;
        $bajaDeRolSelf  = $isSelf && $this->esBajadaDeRol($empleado->puesto, $request->puesto);
        $cambiaPwdAjeno = (!$isSelf) && $request->filled('password');

        if ($request->puesto === 'gerente' && ! $this->puedeAsignarGerente()) {
            return back()->withInput()->with('error', 'No tienes permiso para asignar el rol GERENTE.');
        }

        if (Auth::user()->puesto === 'admin' && $request->puesto !== $empleado->puesto) {
            if ($request->puesto !== 'tecnico') {
                return back()->withInput()->with('error', 'Un ADMIN solo puede asignar el rol TÉCNICO.');
            }
        }

        if ($this->esUltimoDeRol($empleado->id, 'gerente') && $request->puesto !== 'gerente') {
            return back()->withInput()->with('error', 'No puedes cambiar el rol del último GERENTE.');
        }
        if ($this->esUltimoDeRol($empleado->id, 'admin') && $request->puesto !== 'admin') {
            return back()->withInput()->with('error', 'No puedes cambiar el rol del último ADMIN.');
        }

        if ($isSelf && Auth::user()->puesto === 'admin' && $request->puesto === 'gerente') {
            return back()->withInput()->with('error', 'Un ADMIN no puede convertirse en GERENTE.');
        }

        if ($bajaDeRolSelf || $cambiaPwdAjeno) {
            if (! $this->exigirAuthPassword($request)) {
                return back()->withInput()->with('error', 'Contraseña de autorización incorrecta.');
            }
        }

        $empleado->name     = $request->name;
        $empleado->email    = $request->email;
        $empleado->puesto   = $request->puesto;
        $empleado->contacto = $request->contacto;

        if ($request->filled('password')) {
            $empleado->password = Hash::make($request->password);
        }

        $empleado->save();

        return redirect()->route('empleados.index')->with('success', 'Empleado actualizado correctamente.');
    }

    /** ===== Autocomplete (admin: excluye su usuario y a los gerentes) ===== */
    public function autocomplete(Request $request)
    {
        $term = (string) $request->term;
        $me = Auth::user();

        $resultados = User::query()
            ->when($me && $me->puesto === 'admin', function ($q) use ($me) {
                $q->where('id', '<>', $me->id)
                  ->where('puesto', '<>', 'gerente');
            })
            ->where(function ($q) use ($term) {
                $like = "%{$term}%";
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($empleado) {
                return [
                    'label' => $empleado->name . ($empleado->email ? " ({$empleado->email})" : ''),
                    'value' => $empleado->id,
                ];
            });

        return Response::json($resultados);
    }

    /** ===== Eliminar empleado (pide auth_password) ===== */
    public function destroy(Request $request, $id)
    {
        if (Auth::id() == $id) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        if ($this->esUltimoDeRol($id, 'gerente')) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar al último GERENTE.');
        }
        if ($this->esUltimoDeRol($id, 'admin')) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar al último ADMIN.');
        }

        if (! $this->exigirAuthPassword($request)) {
            return back()->with('error', 'Contraseña de autorización incorrecta.');
        }

        User::destroy($id);
        return redirect()->route('empleados.index')->with('success', 'Empleado eliminado correctamente.');
    }

    /** Compatibilidad: no exponer contraseñas */
    public function verPasswordAjax(Request $request)
    {
        return response()->json(['message' => 'No disponible por seguridad. Usa restablecer contraseña.'], 403);
    }
}
