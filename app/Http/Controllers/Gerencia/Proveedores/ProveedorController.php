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
        $request->merge([
            'correo'   => $request->filled('correo') ? mb_strtolower(trim($request->correo)) : null,
            'telefono' => $request->filled('telefono') ? preg_replace('/\D+/', '', $request->telefono) : null,
            'rfc'      => $request->filled('rfc') ? mb_strtoupper(trim($request->rfc)) : null,
            'alias'    => $request->filled('alias') ? trim($request->alias) : null,
        ]);

        $rfcRegex = '/^(?:[A-ZÑ&]{3}|[A-ZÑ&]{4})\d{6}[A-Z0-9]{3}$/';

        $request->validate([
            'nombre'    => 'required|string|max:255',
            'rfc'       => ['required','string','max:20',"regex:$rfcRegex",'unique:proveedores,rfc'],
            'alias'     => 'nullable|string|max:60',
            'direccion' => 'nullable|string|max:255',
            'contacto'  => 'nullable|string|max:255',
            'telefono'  => 'required|digits_between:7,20',
            'correo'    => 'nullable|email|max:255',
        ], [
            'rfc.regex'  => 'El RFC no tiene el formato válido (12/13 caracteres + homoclave).',
            'rfc.unique' => 'Ya existe un proveedor con este RFC.',
        ]);

        Proveedor::create($request->only([
            'nombre','rfc','alias','direccion','contacto','telefono','correo'
        ]));

        $redirect = session()->pull('proveedor_redirect_to');

        return redirect($redirect ?? route('proveedores.index'))
            ->with('success', 'Proveedor registrado correctamente.');
    }

    public function guardardos(Request $request)
    {
        return $this->guardar($request);
    }

    /** === FORMULARIO DE EDICIÓN === */
    public function editar($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        return view('gerencia.proveedores.edit', compact('proveedor'));
    }

    /** === ACTUALIZAR === */
    public function actualizar(Request $request, $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $request->merge([
            'correo'   => $request->filled('correo') ? mb_strtolower(trim($request->correo)) : null,
            'telefono' => $request->filled('telefono') ? preg_replace('/\D+/', '', $request->telefono) : null,
            'rfc'      => $request->filled('rfc') ? mb_strtoupper(trim($request->rfc)) : null,
            'alias'    => $request->filled('alias') ? trim($request->alias) : null,
        ]);

        $rfcRegex = '/^(?:[A-ZÑ&]{3}|[A-ZÑ&]{4})\d{6}[A-Z0-9]{3}$/';

        $request->validate([
            'nombre'    => 'required|string|max:255',
            'rfc'       => [
                'required','string','max:20',"regex:$rfcRegex",
                Rule::unique('proveedores','rfc')->ignore($proveedor->clave_proveedor, 'clave_proveedor'),
            ],
            'alias'     => 'nullable|string|max:60',
            'direccion' => 'nullable|string|max:255',
            'contacto'  => 'nullable|string|max:255',
            'telefono'  => 'required|digits_between:7,20',
            'correo'    => 'nullable|email|max:255',
        ], [
            'rfc.regex'  => 'El RFC no tiene el formato válido (12/13 caracteres + homoclave).',
            'rfc.unique' => 'Ya existe otro proveedor con este RFC.',
        ]);

        $proveedor->update($request->only([
            'nombre','rfc','alias','direccion','contacto','telefono','correo'
        ]));

        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado correctamente.');
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
}
