<?php

namespace App\Http\Controllers\Gerencia\Clientes;

use App\Http\Controllers\Controller;

use App\Models\Cliente;
use App\Models\ClienteDireccionLogistica;
use App\Models\CreditoCliente;
use App\Models\PagoCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $clientes = Cliente::with(['creditoCliente', 'direccionesLogisticas'])
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

        $clientesJson = Cliente::with(['creditoCliente', 'direccionesLogisticas'])
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
            'direcciones_logisticas' => 'required|array|min:1',
            'direcciones_logisticas.*.id' => 'nullable|integer',
            'direcciones_logisticas.*.alias' => 'nullable|string|max:120',
            'direcciones_logisticas.*.direccion_formateada' => 'nullable|string|max:255',
            'direcciones_logisticas.*.place_id' => 'nullable|string|max:255',
            'direcciones_logisticas.*.latitud' => 'nullable|numeric',
            'direcciones_logisticas.*.longitud' => 'nullable|numeric',
            'direcciones_logisticas.*.referencia' => 'nullable|string|max:1000',
            'direcciones_logisticas.*.predeterminada' => 'nullable',
            'direcciones_logisticas.*.verificada_en_mapa' => 'nullable',
            'direcciones_logisticas.*.metodo_verificacion' => 'nullable|string|max:30',
        ], [
            'codigo_cliente.unique'     => 'Ese código de cliente ya existe.',
            'codigo_cliente.alpha_dash' => 'El código solo puede contener letras, números, guiones o guion bajo.',
            'correo.unique'             => 'Este correo electrónico ya está registrado.',
            'direcciones_logisticas.required' => 'Registra al menos una dirección logística del cliente.',
        ]);

        $direcciones = $this->parseDireccionesLogisticas($request);
        $this->validarDireccionesLogisticas($direcciones);

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

        $this->syncDireccionesLogisticas($cliente, $direcciones);
        $this->syncUbicacionLegacy($cliente, $request);

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
        $cliente = Cliente::with('direccionesLogisticas')->findOrFail($id);
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
            'direcciones_logisticas' => 'required|array|min:1',
            'direcciones_logisticas.*.id' => 'nullable|integer',
            'direcciones_logisticas.*.alias' => 'nullable|string|max:120',
            'direcciones_logisticas.*.direccion_formateada' => 'nullable|string|max:255',
            'direcciones_logisticas.*.place_id' => 'nullable|string|max:255',
            'direcciones_logisticas.*.latitud' => 'nullable|numeric',
            'direcciones_logisticas.*.longitud' => 'nullable|numeric',
            'direcciones_logisticas.*.referencia' => 'nullable|string|max:1000',
            'direcciones_logisticas.*.predeterminada' => 'nullable',
            'direcciones_logisticas.*.verificada_en_mapa' => 'nullable',
            'direcciones_logisticas.*.metodo_verificacion' => 'nullable|string|max:30',
        ], [
            'codigo_cliente.unique'     => 'Ese código de cliente ya existe.',
            'codigo_cliente.alpha_dash' => 'El código solo puede contener letras, números, guiones o guion bajo.',
            'correo.unique'             => 'Este correo electrónico ya está registrado.',
            'direcciones_logisticas.required' => 'Registra al menos una dirección logística del cliente.',
        ]);

        $direcciones = $this->parseDireccionesLogisticas($request);
        $this->validarDireccionesLogisticas($direcciones);

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

        $this->syncDireccionesLogisticas($cliente, $direcciones);
        $this->syncUbicacionLegacy($cliente, $request);

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

    protected function parseDireccionesLogisticas(Request $request): Collection
    {
        return collect($request->input('direcciones_logisticas', []))
            ->map(function ($row) {
                return [
                    'id' => !empty($row['id']) ? (int) $row['id'] : null,
                    'alias' => trim((string) ($row['alias'] ?? '')),
                    'direccion_formateada' => trim((string) ($row['direccion_formateada'] ?? '')),
                    'place_id' => trim((string) ($row['place_id'] ?? '')) ?: null,
                    'latitud' => $row['latitud'] !== null && $row['latitud'] !== '' ? (float) $row['latitud'] : null,
                    'longitud' => $row['longitud'] !== null && $row['longitud'] !== '' ? (float) $row['longitud'] : null,
                    'referencia' => trim((string) ($row['referencia'] ?? '')) ?: null,
                    'predeterminada' => !empty($row['predeterminada']),
                    'verificada_en_mapa' => !empty($row['verificada_en_mapa']),
                    'metodo_verificacion' => trim((string) ($row['metodo_verificacion'] ?? '')) ?: null,
                ];
            })
            ->filter(function (array $row) {
                return $row['alias'] !== ''
                    || $row['direccion_formateada'] !== ''
                    || $row['place_id'] !== null
                    || $row['latitud'] !== null
                    || $row['longitud'] !== null;
            })
            ->values();
    }

    protected function syncDireccionesLogisticas(Cliente $cliente, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $cliente->direccionesLogisticas()->delete();
            return;
        }

        $predeterminadaAsignada = false;
        $idsConservados = [];

        foreach ($rows as $index => $row) {
            $direccion = $row['id']
                ? $cliente->direccionesLogisticas()->whereKey($row['id'])->first()
                : new ClienteDireccionLogistica();

            if (!$direccion) {
                $direccion = new ClienteDireccionLogistica();
            }

            $direccion->clave_cliente = $cliente->clave_cliente;
            $direccion->alias = $row['alias'];
            $direccion->direccion_formateada = $row['direccion_formateada'];
            $direccion->place_id = $row['place_id'];
            $direccion->latitud = $row['latitud'];
            $direccion->longitud = $row['longitud'];
            $direccion->referencia = $row['referencia'];
            $direccion->activa = true;
            $direccion->predeterminada = !$predeterminadaAsignada && ($row['predeterminada'] || $index === 0);
            $direccion->verificada_en_mapa = (bool) $row['verificada_en_mapa'];
            $direccion->metodo_verificacion = $row['metodo_verificacion'];
            $direccion->save();

            $predeterminadaAsignada = $predeterminadaAsignada || $direccion->predeterminada;
            $idsConservados[] = $direccion->id;
        }

        $cliente->direccionesLogisticas()
            ->when(!empty($idsConservados), fn ($q) => $q->whereNotIn('id', $idsConservados))
            ->when(empty($idsConservados), fn ($q) => $q)
            ->delete();
    }

    protected function validarDireccionesLogisticas(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'direcciones_logisticas' => 'Registra al menos una dirección logística del cliente.',
            ]);
        }

        $errors = [];
        $aliases = [];
        $predeterminadas = 0;

        foreach ($rows as $index => $row) {
            if ($row['predeterminada']) {
                $predeterminadas++;
            }

            if ($row['alias'] === '') {
                $errors["direcciones_logisticas.$index.alias"] = 'Cada dirección logística necesita un alias.';
            } else {
                $aliasNormalizado = mb_strtolower($row['alias']);
                if (in_array($aliasNormalizado, $aliases, true)) {
                    $errors["direcciones_logisticas.$index.alias"] = 'No repitas alias de direcciones dentro del mismo cliente.';
                }
                $aliases[] = $aliasNormalizado;
            }

            if ($row['direccion_formateada'] === '') {
                $errors["direcciones_logisticas.$index.direccion_formateada"] = 'Selecciona la dirección desde el mapa.';
            }

            if ($row['place_id'] === null || $row['latitud'] === null || $row['longitud'] === null) {
                $errors["direcciones_logisticas.$index.place_id"] = 'La dirección debe incluir coordenadas válidas.';
            }

            if (!$row['verificada_en_mapa']) {
                $errors["direcciones_logisticas.$index.verificada_en_mapa"] = 'La dirección debe quedar verificada en mapa.';
            }

            if (!in_array($row['metodo_verificacion'], ['autocomplete', 'mapa'], true)) {
                $errors["direcciones_logisticas.$index.metodo_verificacion"] = 'Selecciona la dirección usando el mapa o el buscador.';
            }
        }

        if ($predeterminadas < 1) {
            $errors['direcciones_logisticas'] = 'Selecciona una dirección principal del cliente.';
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    protected function syncUbicacionLegacy(Cliente $cliente, Request $request): void
    {
        $principal = $cliente->direccionesLogisticas()
            ->where('predeterminada', true)
            ->first();

        $cliente->ubicacion = $principal?->direccion_formateada ?: ($request->input('ubicacion') ?: null);
        $cliente->save();
    }
}
