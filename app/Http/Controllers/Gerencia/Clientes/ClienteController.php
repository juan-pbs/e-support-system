<?php

namespace App\Http\Controllers\Gerencia\Clientes;

use App\Http\Controllers\Controller;

use App\Models\Cliente;
use App\Models\CreditoCliente;
use App\Models\PagoCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $buscarRaw = trim((string) $request->input('buscar', ''));

        // ✅ Si viene "CODIGO - Nombre", extraemos solo el código
        $buscarCodigo = $buscarRaw;
        if ($buscarRaw !== '' && Str::contains($buscarRaw, ' - ')) {
            $buscarCodigo = trim(Str::before($buscarRaw, ' - '));
        }

        $clientes = Cliente::with('creditoCliente')
            ->when($buscarRaw !== '', function ($query) use ($buscarRaw, $buscarCodigo) {
                $query->where(function ($w) use ($buscarRaw, $buscarCodigo) {
                    // ✅ Buscar por código "puro" (cuando viene del sugerido)
                    $w->where('codigo_cliente', 'like', '%' . $buscarCodigo . '%')
                      ->orWhere('codigo_cliente', $buscarCodigo)

                      // ✅ También permitir buscar por lo que escribió el usuario
                      ->orWhere('codigo_cliente', 'like', '%' . $buscarRaw . '%')
                      ->orWhere('nombre', 'like', '%' . $buscarRaw . '%')
                      ->orWhere('nombre_empresa', 'like', '%' . $buscarRaw . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $clientesJson = Cliente::with('creditoCliente')
            ->when($buscarRaw !== '', function ($query) use ($buscarRaw, $buscarCodigo) {
                $query->where(function ($w) use ($buscarRaw, $buscarCodigo) {
                    $w->where('codigo_cliente', 'like', '%' . $buscarCodigo . '%')
                      ->orWhere('codigo_cliente', $buscarCodigo)
                      ->orWhere('codigo_cliente', 'like', '%' . $buscarRaw . '%')
                      ->orWhere('nombre', 'like', '%' . $buscarRaw . '%')
                      ->orWhere('nombre_empresa', 'like', '%' . $buscarRaw . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $pagos = PagoCredito::all();

        return view('gerencia.clientes.index', [
            'clientes'     => $clientes,
            'clientesJson' => $clientesJson,
            'pagos'        => $pagos,
        ]);
    }

    public function crear(Request $request)
    {
        $redirectTo = $request->query('redirect');

        if (!$redirectTo) {
            $redirectTo = url()->previous();
        }

        $redirectTo = trim((string) $redirectTo);

        $hostActual  = $request->getSchemeAndHttpHost();
        $esRelativa  = Str::startsWith($redirectTo, '/');
        $esMismoHost = Str::startsWith($redirectTo, $hostActual);

        if (!$esRelativa && !$esMismoHost) {
            $redirectTo = url()->previous();
            if (!$redirectTo || $redirectTo === $request->fullUrl()) {
                $redirectTo = route('clientes');
            }
        }

        $request->session()->put('clientes.redirect_to', $redirectTo);

        return view('gerencia.clientes.create', [
            'redirectTo' => $redirectTo,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo_cliente'     => ['required','string','max:60','alpha_dash','unique:cliente,codigo_cliente'],
            'nombre'             => 'required|string|max:255',
            'empresa'            => 'nullable|string|max:255',
            'telefono'           => 'required|digits_between:7,20',
            'contacto_adicional' => 'nullable|digits_between:7,20',
            'correo'             => 'required|email|max:255|unique:cliente,correo_electronico',
            'ubicacion'          => 'nullable|string|max:255',
            'direccion_fiscal'   => 'required|string|max:255',
            'datos_fiscales'     => 'nullable|string|max:13',
            'contacto'           => 'nullable|string|max:255',
            'redirect_to'        => 'nullable|string',
        ], [
            'codigo_cliente.unique'     => 'Ese código de cliente ya existe.',
            'codigo_cliente.alpha_dash' => 'El código solo puede contener letras, números, guiones o guion bajo.',
            'correo.unique'             => 'Este correo electrónico ya está registrado.',
        ]);

        $cliente = Cliente::create([
            'codigo_cliente'     => $request->codigo_cliente,
            'nombre'             => $request->nombre,
            'nombre_empresa'     => $request->empresa,
            'telefono'           => $request->telefono,
            'contacto_adicional' => $request->contacto_adicional,
            'correo_electronico' => $request->correo,
            'ubicacion'          => $request->ubicacion,
            'direccion_fiscal'   => $request->direccion_fiscal,
            'datos_fiscales'     => $request->datos_fiscales,
            'contacto'           => $request->contacto,
        ]);

        $redirectTo = $request->input('redirect_to')
            ?: $request->session()->pull('clientes.redirect_to')
            ?: route('clientes');

        $redirectTo = trim((string) $redirectTo);

        $hostActual  = $request->getSchemeAndHttpHost();
        $esRelativa  = Str::startsWith($redirectTo, '/');
        $esMismoHost = Str::startsWith($redirectTo, $hostActual);

        if (!$esRelativa && !$esMismoHost) {
            $redirectTo = route('clientes');
        }

        $flash = ['success' => 'Cliente registrado correctamente.'];

        $cotCreateUrl = route('cotizaciones.crear');
        if ($redirectTo === $cotCreateUrl || Str::contains($redirectTo, $cotCreateUrl)) {
            $flash['cliente_id'] = $cliente->clave_cliente;
        }

        return redirect()->to($redirectTo)->with($flash);
    }

    public function edit($id)
    {
        $cliente = Cliente::findOrFail($id);
        return view('gerencia.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        $request->validate([
            'codigo_cliente'     => ['required','string','max:60','alpha_dash','unique:cliente,codigo_cliente,' . $cliente->clave_cliente . ',clave_cliente'],
            'nombre'             => 'required|string|max:255',
            'empresa'            => 'nullable|string|max:255',
            'telefono'           => 'required|digits_between:7,20',
            'contacto_adicional' => 'nullable|digits_between:7,20',
            'correo'             => 'required|email|max:255|unique:cliente,correo_electronico,' . $cliente->clave_cliente . ',clave_cliente',
            'ubicacion'          => 'nullable|string|max:255',
            'direccion_fiscal'   => 'required|string|max:255',
            'datos_fiscales'     => 'nullable|string|max:13',
            'contacto'           => 'nullable|string|max:255',
        ], [
            'codigo_cliente.unique'     => 'Ese código de cliente ya existe.',
            'codigo_cliente.alpha_dash' => 'El código solo puede contener letras, números, guiones o guion bajo.',
            'correo.unique'             => 'Este correo electrónico ya está registrado.',
        ]);

        $cliente->update([
            'codigo_cliente'     => $request->codigo_cliente,
            'nombre'             => $request->nombre,
            'nombre_empresa'     => $request->empresa,
            'telefono'           => $request->telefono,
            'contacto_adicional' => $request->contacto_adicional,
            'correo_electronico' => $request->correo,
            'ubicacion'          => $request->ubicacion,
            'direccion_fiscal'   => $request->direccion_fiscal,
            'datos_fiscales'     => $request->datos_fiscales,
            'contacto'           => $request->contacto,
        ]);

        return redirect()->route('clientes')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy($id)
    {
        $cliente = Cliente::findOrFail($id);

        $cliente->pagos()->delete();

        if ($cliente->creditoCliente) {
            $cliente->creditoCliente->delete();
        }

        $cliente->delete();

        return redirect()->route('clientes')->with('success', 'Cliente, crédito e historial de pagos eliminados correctamente.');
    }
    public function autocompleteSelect(Request $request)
{
    $q = trim((string) $request->get('q', ''));

    if ($q === '' || mb_strlen($q) < 2) {
        return response()->json([]);
    }

    $clientes = \App\Models\Cliente::query()
        ->select('clave_cliente', 'nombre', 'correo_electronico', 'nombre_empresa')
        ->where(function ($w) use ($q) {
            $w->where('nombre', 'like', "%{$q}%")
              ->orWhere('nombre_empresa', 'like', "%{$q}%")
              ->orWhere('correo_electronico', 'like', "%{$q}%");
        })
        ->orderBy('nombre')
        ->limit(15)
        ->get()
        ->map(function ($c) {
            $nombre = $c->nombre ?: ($c->nombre_empresa ?: 'Cliente');
            $correo = $c->correo_electronico ?: '';
            return [
                'id'   => $c->clave_cliente,
                'text' => trim($nombre . ' - ' . $correo),
            ];
        });

    return response()->json($clientes);
}

    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->input('term', ''));

        $clientes = Cliente::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('codigo_cliente', 'like', '%' . $term . '%')
                      ->orWhere('nombre', 'like', '%' . $term . '%')
                      ->orWhere('nombre_empresa', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(function ($cliente) {
                return [
                    'id'    => $cliente->clave_cliente,
                    'label' => ($cliente->codigo_cliente ? $cliente->codigo_cliente . ' - ' : '') . $cliente->nombre,
                ];
            });

        return response()->json($clientes);
    }

    public function actualizarCredito(Request $request, $id)
    {
        $request->validate([
            'monto_maximo'     => 'required|numeric|min:0',
            'fecha_asignacion' => 'required|date',
        ]);

        $fechaLimite = Carbon::parse($request->fecha_asignacion)->startOfDay();
        $hoy = now()->startOfDay();
        $diasRestantes = (int) $hoy->diffInDays($fechaLimite, false);

        $estatus = $diasRestantes <= 0 ? 'vencido' : 'activo';
        $diasCreditoGuardar = max(0, $diasRestantes);

        $credito = CreditoCliente::where('clave_cliente', $id)->first();

        if ($credito && $request->monto_maximo < $credito->monto_usado) {
            return back()->withErrors([
                'monto_maximo' => 'No puede ser menor al crédito usado actual (' . number_format($credito->monto_usado, 2) . ').'
            ])->withInput();
        }

        if ($credito) {
            $credito->update([
                'monto_maximo'     => $request->monto_maximo,
                'dias_credito'     => $diasCreditoGuardar,
                'fecha_asignacion' => $request->fecha_asignacion,
                'estatus'          => $estatus,
            ]);
        } else {
            CreditoCliente::create([
                'clave_cliente'    => $id,
                'monto_maximo'     => $request->monto_maximo,
                'monto_usado'      => 0,
                'dias_credito'     => $diasCreditoGuardar,
                'fecha_asignacion' => $request->fecha_asignacion,
                'estatus'          => $estatus,
            ]);
        }

        return back()->with('success', 'Crédito actualizado correctamente.');
    }

    public function registrarPago(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
        ]);

        $cliente = Cliente::with('creditoCliente')->findOrFail($id);
        $credito = $cliente->creditoCliente;

        if (!$credito || $credito->monto_usado <= 0) {
            return back()->withErrors(['error' => 'Este cliente no tiene crédito usado. No se puede registrar un pago.']);
        }

        if ($request->monto > $credito->monto_usado) {
            return back()->withErrors(['error' => 'El monto del pago no puede ser mayor al crédito usado.']);
        }

        $pago = new PagoCredito([
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
            'fecha' => now(),
        ]);

        $cliente->pagos()->save($pago);

        $credito->monto_usado = max(0, $credito->monto_usado - $request->monto);
        $credito->save();

        return back()->with('success', 'Pago registrado correctamente.');
    }

    public function mostrarPagos($id)
    {
        $pagos = PagoCredito::where('clave_cliente', $id)
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json($pagos);
    }
}
