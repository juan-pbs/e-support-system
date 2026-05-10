<?php

namespace App\Http\Controllers\Gerencia\Proveedores;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Proveedor;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ProveedorController extends Controller
{
    /** Listado + búsqueda */
    public function index(Request $request)
    {
        $buscarRaw = trim((string) $request->input('buscar', ''));

        // ✅ Intentar extraer RFC / correo / nombre cuando viene del sugerido
        $buscarNombre = $buscarRaw;
        $buscarRfc    = null;
        $buscarEmail  = null;

        if ($buscarRaw !== '') {
            // Ejemplo label: "ACME · RFC: ABC123... · correo@x.com"
            // Separar por "·"
            $parts = array_map('trim', explode('·', $buscarRaw));

            if (count($parts) > 0) {
                // Primer parte suele ser el nombre
                $buscarNombre = trim((string) ($parts[0] ?? $buscarRaw));
            }

            foreach ($parts as $p) {
                // RFC: XXXXX
                if (Str::contains(mb_strtolower($p), 'rfc:')) {
                    $val = trim(str_ireplace('rfc:', '', $p));
                    if ($val !== '') $buscarRfc = mb_strtoupper($val);
                }

                // correo (si parece email)
                $maybeEmail = trim($p);
                if ($maybeEmail !== '' && filter_var($maybeEmail, FILTER_VALIDATE_EMAIL)) {
                    $buscarEmail = mb_strtolower($maybeEmail);
                }
            }
        }

        $proveedores = Proveedor::query()
            ->when($buscarRaw !== '', function ($q) use ($buscarRaw, $buscarNombre, $buscarRfc, $buscarEmail) {
                $likeRaw = "%{$buscarRaw}%";
                $likeNom = "%{$buscarNombre}%";

                $q->where(function ($w) use ($likeRaw, $likeNom, $buscarRfc, $buscarEmail) {
                    // ✅ búsqueda normal
                    $w->where('nombre', 'like', $likeRaw)
                      ->orWhere('correo', 'like', $likeRaw)
                      ->orWhere('telefono', 'like', $likeRaw)
                      ->orWhere('rfc', 'like', $likeRaw)
                      ->orWhere('alias', 'like', $likeRaw)
                      ->orWhere('direccion', 'like', $likeRaw);

                    // ✅ por si viene el nombre limpio
                    $w->orWhere('nombre', 'like', $likeNom);

                    // ✅ por si viene RFC extraído
                    if ($buscarRfc) {
                        $w->orWhere('rfc', $buscarRfc)
                          ->orWhere('rfc', 'like', "%{$buscarRfc}%");
                    }

                    // ✅ por si viene correo extraído
                    if ($buscarEmail) {
                        $w->orWhere('correo', $buscarEmail)
                          ->orWhere('correo', 'like', "%{$buscarEmail}%");
                    }
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('gerencia.proveedores.index', compact('proveedores'));
    }

    /** === FORMULARIO DE ALTA === */
    public function crear(Request $request)
    {
        if ($request->filled('redirect')) {
            session()->put('proveedor_redirect_to', $request->redirect);
        }
        return view('gerencia.proveedores.create');
    }

    public function nuevo(Request $request)
    {
        return $this->crear($request);
    }

    public function create(Request $request, $codigo_producto = null)
    {
        if ($request->filled('redirect')) {
            session()->put('proveedor_redirect_to', $request->redirect);
        }
        return view('gerencia.proveedores.create');
    }

    /** === GUARDAR === */
    public function guardar(Request $request)
    {
        $payload = $this->validatedPayload($request);

        Proveedor::create($payload);

        $redirect = session()->pull('proveedor_redirect_to');

        return redirect($redirect ?? route('proveedores.index'))
            ->with('success', 'Proveedor registrado correctamente.');
    }

    public function guardardos(Request $request)
    {
        return $this->guardar($request);
    }

    /** === FORMULARIO DE EDICIÓN === */
    public function editar(Request $request, $id)
    {
        if ($request->filled('redirect')) {
            session()->put('proveedor_redirect_to', $request->redirect);
        }

        $proveedor = Proveedor::findOrFail($id);
        $redirectTo = $request->input('redirect') ?: session('proveedor_redirect_to');

        return view('gerencia.proveedores.edit', compact('proveedor', 'redirectTo'));
    }

    /** === ACTUALIZAR === */
    public function actualizar(Request $request, $id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $payload = $this->validatedPayload($request, $proveedor);

        $proveedor->update($payload);

        $redirect = $request->input('redirect_to') ?: session()->pull('proveedor_redirect_to');

        return redirect($redirect ?: route('proveedores.index'))->with('success', 'Proveedor actualizado correctamente.');
    }

    /** === ELIMINAR === */
    public function eliminar($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->delete();

        return redirect()->route('proveedores.index')->with('success', 'Proveedor eliminado correctamente.');
    }

    /** === AUTOCOMPLETE === */
    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->input('term', ''));

        $items = Proveedor::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = "%{$term}%";
                $q->where(function ($w) use ($like) {
                    $w->where('nombre', 'like', $like)
                      ->orWhere('correo', 'like', $like)
                      ->orWhere('telefono', 'like', $like)
                      ->orWhere('rfc', 'like', $like)
                      ->orWhere('alias', 'like', $like);
                });
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                $label = $p->nombre;
                if ($p->rfc)    $label .= " · RFC: {$p->rfc}";
                if ($p->correo) $label .= " · {$p->correo}";
                return ['id' => $p->clave_proveedor, 'label' => $label];
            });

        return response()->json($items);
    }

    private function validatedPayload(Request $request, ?Proveedor $proveedor = null): array
    {
        $request->merge([
            'correo'   => $request->filled('correo') ? mb_strtolower(trim($request->correo)) : null,
            'telefono' => $request->filled('telefono') ? preg_replace('/\D+/', '', $request->telefono) : null,
            'rfc'      => $request->filled('rfc') ? mb_strtoupper(trim($request->rfc)) : null,
            'alias'    => $request->filled('alias') ? trim($request->alias) : null,
        ]);

        $rfcRegex = '/^(?:[A-ZÑ&]{3}|[A-ZÑ&]{4})\d{6}[A-Z0-9]{3}$/';
        $rfcRule = $proveedor
            ? Rule::unique('proveedores', 'rfc')->ignore($proveedor->clave_proveedor, 'clave_proveedor')
            : 'unique:proveedores,rfc';

        $validated = $request->validate([
            'nombre'    => 'required|string|max:255',
            'rfc'       => ['required', 'string', 'max:20', "regex:$rfcRegex", $rfcRule],
            'alias'     => 'nullable|string|max:60',
            'direccion_logistica' => 'nullable|string|max:255',
            'direccion_logistica_place_id' => 'nullable|string|max:255',
            'direccion_logistica_latitud' => 'nullable|numeric',
            'direccion_logistica_longitud' => 'nullable|numeric',
            'direccion_logistica_referencia' => 'nullable|string|max:1000',
            'direccion_logistica_verificada_en_mapa' => 'nullable',
            'direccion_logistica_metodo' => 'nullable|in:autocomplete,mapa',
            'contacto'  => 'nullable|string|max:255',
            'telefono'  => 'required|digits_between:7,20',
            'correo'    => 'nullable|email|max:255',
        ], [
            'rfc.regex'  => 'El RFC no tiene el formato válido (12/13 caracteres + homoclave).',
            'rfc.unique' => $proveedor ? 'Ya existe otro proveedor con este RFC.' : 'Ya existe un proveedor con este RFC.',
            'direccion_logistica.required' => 'Selecciona la dirección del proveedor desde el mapa.',
            'direccion_logistica_place_id.required' => 'La dirección del proveedor debe quedar vinculada a un punto real.',
            'direccion_logistica_verificada_en_mapa.accepted' => 'La dirección del proveedor debe quedar verificada en mapa.',
            'direccion_logistica_metodo.required' => 'Selecciona la dirección del proveedor usando el mapa o el buscador.',
        ]);

        $tieneDireccionLogistica = $this->tieneDireccionLogistica($validated);

        if ($tieneDireccionLogistica) {
            $request->validate([
                'direccion_logistica' => 'required|string|max:255',
                'direccion_logistica_place_id' => 'required|string|max:255',
                'direccion_logistica_latitud' => 'required|numeric',
                'direccion_logistica_longitud' => 'required|numeric',
                'direccion_logistica_verificada_en_mapa' => 'accepted',
                'direccion_logistica_metodo' => 'required|in:autocomplete,mapa',
            ], [
                'direccion_logistica.required' => 'Selecciona la direccion del proveedor desde el mapa.',
                'direccion_logistica_place_id.required' => 'La direccion del proveedor debe quedar vinculada a un punto real.',
                'direccion_logistica_latitud.required' => 'La direccion del proveedor debe incluir coordenadas validas.',
                'direccion_logistica_longitud.required' => 'La direccion del proveedor debe incluir coordenadas validas.',
                'direccion_logistica_verificada_en_mapa.accepted' => 'La direccion del proveedor debe quedar verificada en mapa.',
                'direccion_logistica_metodo.required' => 'Selecciona la direccion del proveedor usando el mapa o el buscador.',
            ]);
        }

        return [
            'nombre' => $validated['nombre'],
            'rfc' => $validated['rfc'],
            'alias' => $validated['alias'] ?? null,
            'direccion' => $tieneDireccionLogistica ? $validated['direccion_logistica'] : null,
            'direccion_logistica' => $tieneDireccionLogistica ? $validated['direccion_logistica'] : null,
            'direccion_logistica_place_id' => $tieneDireccionLogistica ? ($validated['direccion_logistica_place_id'] ?? null) : null,
            'direccion_logistica_latitud' => $tieneDireccionLogistica ? $validated['direccion_logistica_latitud'] : null,
            'direccion_logistica_longitud' => $tieneDireccionLogistica ? $validated['direccion_logistica_longitud'] : null,
            'direccion_logistica_referencia' => $tieneDireccionLogistica ? (trim((string) ($validated['direccion_logistica_referencia'] ?? '')) ?: null) : null,
            'direccion_logistica_verificada_en_mapa' => $tieneDireccionLogistica,
            'direccion_logistica_metodo' => $tieneDireccionLogistica ? $validated['direccion_logistica_metodo'] : null,
            'contacto' => trim((string) ($validated['contacto'] ?? '')) ?: null,
            'telefono' => $validated['telefono'],
            'correo' => $validated['correo'] ?? null,
        ];
    }

    private function tieneDireccionLogistica(array $validated): bool
    {
        return trim((string) ($validated['direccion_logistica'] ?? '')) !== ''
            || trim((string) ($validated['direccion_logistica_place_id'] ?? '')) !== ''
            || ($validated['direccion_logistica_latitud'] ?? null) !== null
            || ($validated['direccion_logistica_longitud'] ?? null) !== null;
    }
}
