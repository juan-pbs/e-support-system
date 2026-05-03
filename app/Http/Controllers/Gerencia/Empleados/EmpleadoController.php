<?php

namespace App\Http\Controllers\Gerencia\Empleados;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmpleadoController extends Controller
{
    private function normalizeRole(?string $role): string
    {
        return is_string($role) ? mb_strtolower(trim($role)) : '';
    }

    private function actorRole(): string
    {
        return $this->normalizeRole(Auth::user()?->puesto);
    }

    private function contarPorRol(string $role): int
    {
        return User::where('puesto', $this->normalizeRole($role))->count();
    }

    private function esUltimoDeRol(int $id, string $role): bool
    {
        $role = $this->normalizeRole($role);

        return $this->contarPorRol($role) === 1
            && User::where('id', $id)->where('puesto', $role)->exists();
    }

    private function puedeAsignarGerente(): bool
    {
        return in_array($this->actorRole(), ['gerente', 'sistema'], true);
    }

    private function puedeAsignarSistema(): bool
    {
        return $this->actorRole() === 'sistema';
    }

    private function rolesDisponiblesParaActor(): array
    {
        return match ($this->actorRole()) {
            'sistema' => ['sistema', 'gerente', 'admin', 'tecnico'],
            'gerente' => ['gerente', 'admin', 'tecnico'],
            'admin' => ['tecnico'],
            default => [],
        };
    }

    private function puedeGestionarRol(?string $targetRole): bool
    {
        $targetRole = $this->normalizeRole($targetRole);

        return match ($this->actorRole()) {
            'sistema' => in_array($targetRole, ['sistema', 'gerente', 'admin', 'tecnico'], true),
            'gerente' => in_array($targetRole, ['gerente', 'admin', 'tecnico'], true),
            'admin' => $targetRole === 'tecnico',
            default => false,
        };
    }

    private function aplicarVisibilidadPorRol($query, ?User $actor)
    {
        if (! $actor) {
            return $query->whereRaw('1 = 0');
        }

        return match ($this->normalizeRole($actor->puesto)) {
            'sistema' => $query,
            'gerente' => $query->where('puesto', '<>', 'sistema'),
            'admin' => $query
                ->where('id', '<>', $actor->id)
                ->where('puesto', 'tecnico'),
            default => $query->where('id', $actor->id),
        };
    }

    private function rolRank(?string $role): int
    {
        return match ($this->normalizeRole($role)) {
            'tecnico' => 1,
            'admin' => 2,
            'gerente' => 3,
            'sistema' => 4,
            default => 0,
        };
    }

    private function esBajadaDeRol(?string $from, ?string $to): bool
    {
        return $this->rolRank($to) < $this->rolRank($from);
    }

    private function exigirAuthPassword(Request $request): bool
    {
        $password = (string) $request->input('auth_password', '');

        return $password !== '' && Hash::check($password, Auth::user()->password);
    }

    public function index(Request $request)
    {
        $busquedaRaw = trim((string) $request->input('busqueda', ''));
        $me = Auth::user();

        $busquedaNombre = $busquedaRaw;
        $busquedaEmail = null;

        if ($busquedaRaw !== '' && Str::contains($busquedaRaw, '(') && Str::contains($busquedaRaw, ')')) {
            $posIni = mb_strrpos($busquedaRaw, '(');
            $posFin = mb_strrpos($busquedaRaw, ')');

            if ($posIni !== false && $posFin !== false && $posFin > $posIni) {
                $inside = trim(mb_substr($busquedaRaw, $posIni + 1, $posFin - $posIni - 1));
                $namePart = trim(mb_substr($busquedaRaw, 0, $posIni));

                if ($inside !== '' && filter_var($inside, FILTER_VALIDATE_EMAIL)) {
                    $busquedaEmail = mb_strtolower($inside);
                    $busquedaNombre = $namePart !== '' ? $namePart : $busquedaRaw;
                }
            }
        }

        $empleados = $this->aplicarVisibilidadPorRol(User::query(), $me)
            ->when($busquedaRaw !== '', function ($query) use ($busquedaRaw, $busquedaNombre, $busquedaEmail) {
                $likeRaw = "%{$busquedaRaw}%";
                $likeNombre = "%{$busquedaNombre}%";

                $query->where(function ($inner) use ($likeRaw, $likeNombre, $busquedaEmail) {
                    $inner->where('name', 'like', $likeRaw)
                        ->orWhere('email', 'like', $likeRaw)
                        ->orWhere('name', 'like', $likeNombre);

                    if ($busquedaEmail) {
                        $inner->orWhere('email', $busquedaEmail)
                            ->orWhere('email', 'like', "%{$busquedaEmail}%");
                    }
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $busqueda = $busquedaRaw;

        return view('gerencia.empleados.index', compact('empleados', 'busqueda'));
    }

    public function create()
    {
        return view('gerencia.empleados.create');
    }

    public function store(Request $request)
    {
        $rolesDisponibles = $this->rolesDisponiblesParaActor();

        if (empty($rolesDisponibles)) {
            return back()->withInput()->with('error', 'No tienes permiso para registrar empleados.');
        }

        $request->merge([
            'name' => trim((string) $request->name),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->email)) : null,
            'contacto' => $request->filled('contacto') ? preg_replace('/\D+/', '', $request->contacto) : null,
            'puesto' => $request->filled('puesto') ? $this->normalizeRole($request->puesto) : null,
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'puesto' => ['required', Rule::in($rolesDisponibles)],
            'contacto' => 'nullable|string|max:20',
        ]);

        if ($request->puesto === 'sistema' && ! $this->puedeAsignarSistema()) {
            return back()->withInput()->with('error', 'Solo un usuario SISTEMA puede crear otro usuario SISTEMA.');
        }

        if ($request->puesto === 'gerente' && ! $this->puedeAsignarGerente()) {
            return back()->withInput()->with('error', 'No tienes permiso para asignar el rol GERENTE.');
        }

        if (! $this->exigirAuthPassword($request)) {
            return back()->withInput()->with('error', 'Contrasena de autorizacion incorrecta.');
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'puesto' => $request->puesto,
            'contacto' => $request->contacto,
            'remember_token' => Str::random(60),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('empleados.index')->with('success', 'Empleado registrado correctamente.');
    }

    public function edit($id)
    {
        $empleado = User::findOrFail($id);

        if (! $this->puedeGestionarRol($empleado->puesto)) {
            return redirect()->route('empleados.index')
                ->with('error', 'No tienes permiso para editar este usuario.');
        }

        return view('gerencia.empleados.edit', compact('empleado'));
    }

    public function update(Request $request, $id)
    {
        $empleado = User::findOrFail($id);
        $rolesDisponibles = $this->rolesDisponiblesParaActor();

        if (! $this->puedeGestionarRol($empleado->puesto)) {
            return redirect()->route('empleados.index')
                ->with('error', 'No tienes permiso para editar este usuario.');
        }

        if (empty($rolesDisponibles)) {
            return back()->withInput()->with('error', 'No tienes permiso para actualizar empleados.');
        }

        $request->merge([
            'name' => trim((string) $request->name),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->email)) : null,
            'contacto' => $request->filled('contacto') ? preg_replace('/\D+/', '', $request->contacto) : null,
            'puesto' => $request->filled('puesto') ? $this->normalizeRole($request->puesto) : null,
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($empleado->id)],
            'puesto' => ['required', Rule::in($rolesDisponibles)],
            'contacto' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $isSelf = Auth::id() === $empleado->id;
        $bajaDeRolSelf = $isSelf && $this->esBajadaDeRol($empleado->puesto, $request->puesto);
        $cambiaPwdAjeno = (! $isSelf) && $request->filled('password');

        if ($request->puesto === 'sistema' && ! $this->puedeAsignarSistema()) {
            return back()->withInput()->with('error', 'Solo un usuario SISTEMA puede asignar el rol SISTEMA.');
        }

        if ($request->puesto === 'gerente' && ! $this->puedeAsignarGerente()) {
            return back()->withInput()->with('error', 'No tienes permiso para asignar el rol GERENTE.');
        }

        if ($this->esUltimoDeRol($empleado->id, 'gerente') && $request->puesto !== 'gerente') {
            return back()->withInput()->with('error', 'No puedes cambiar el rol del ultimo GERENTE.');
        }

        if ($this->esUltimoDeRol($empleado->id, 'admin') && $request->puesto !== 'admin') {
            return back()->withInput()->with('error', 'No puedes cambiar el rol del ultimo ADMIN.');
        }

        if ($this->esUltimoDeRol($empleado->id, 'sistema') && $request->puesto !== 'sistema') {
            return back()->withInput()->with('error', 'No puedes cambiar el rol del ultimo SISTEMA.');
        }

        if ($bajaDeRolSelf || $cambiaPwdAjeno) {
            if (! $this->exigirAuthPassword($request)) {
                return back()->withInput()->with('error', 'Contrasena de autorizacion incorrecta.');
            }
        }

        $empleado->name = $request->name;
        $empleado->email = $request->email;
        $empleado->puesto = $request->puesto;
        $empleado->contacto = $request->contacto;

        if ($request->filled('password')) {
            $empleado->password = Hash::make($request->password);
        }

        $empleado->save();

        return redirect()->route('empleados.index')->with('success', 'Empleado actualizado correctamente.');
    }

    public function autocomplete(Request $request)
    {
        $term = (string) $request->term;
        $me = Auth::user();

        $resultados = $this->aplicarVisibilidadPorRol(User::query(), $me)
            ->where(function ($query) use ($term) {
                $like = "%{$term}%";

                $query->where('name', 'like', $like)
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

    public function destroy(Request $request, $id)
    {
        if (Auth::id() == $id) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $empleado = User::findOrFail($id);

        if (! $this->puedeGestionarRol($empleado->puesto)) {
            return redirect()->route('empleados.index')
                ->with('error', 'No tienes permiso para eliminar este usuario.');
        }

        if ($this->esUltimoDeRol($id, 'gerente')) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar al ultimo GERENTE.');
        }

        if ($this->esUltimoDeRol($id, 'admin')) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar al ultimo ADMIN.');
        }

        if ($this->esUltimoDeRol($id, 'sistema')) {
            return redirect()->route('empleados.index')->with('error', 'No puedes eliminar al ultimo SISTEMA.');
        }

        if (! $this->exigirAuthPassword($request)) {
            return back()->with('error', 'Contrasena de autorizacion incorrecta.');
        }

        $empleado->delete();

        return redirect()->route('empleados.index')->with('success', 'Empleado eliminado correctamente.');
    }

    public function verPasswordAjax(Request $request)
    {
        return response()->json(['message' => 'No disponible por seguridad. Usa restablecer contrasena.'], 403);
    }
}
