<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CatalogoProductoController extends Controller
{
    /** Expresión de agregación para concatenar proveedores según driver */
    private function proveedoresAggExpr(): string
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'])) {
            return "GROUP_CONCAT(DISTINCT proveedores.nombre ORDER BY proveedores.nombre SEPARATOR ', ')";
        }
        if ($driver === 'pgsql') {
            return "STRING_AGG(DISTINCT proveedores.nombre, ', ')";
        }
        return "GROUP_CONCAT(DISTINCT proveedores.nombre)";
    }

    /** Listado con filtros */
    public function index(Request $request)
    {
        $subStock = '(SELECT COALESCE(SUM(paquetes_restantes * COALESCE(piezas_por_paquete,1) + COALESCE(piezas_sueltas,0)),0)
                      FROM inventario WHERE inventario.codigo_producto = productos.codigo_producto)';

        $aggProv = $this->proveedoresAggExpr();

        // ✅ Incluimos created_at para evitar problemas con orderBy + groupBy
        $prodCols = [
            'productos.codigo_producto',
            'productos.nombre',
            'productos.numero_parte',
            'productos.categoria',
            'productos.clave_prodserv',
            'productos.unidad',
            'productos.descripcion',
            'productos.activo',
            'productos.stock_seguridad',
            'productos.imagen',
            'productos.created_at',
        ];

        $query = Producto::query()
            ->from('productos')
            ->leftJoin('inventario', 'inventario.codigo_producto', '=', 'productos.codigo_producto')
            ->leftJoin('proveedores', 'proveedores.clave_proveedor', '=', 'inventario.clave_proveedor')
            ->select(array_merge($prodCols, [
                DB::raw("$subStock AS stock_total"),
                DB::raw("$aggProv AS proveedores_str"),
            ]))
            ->groupBy($prodCols);

        // ✅ Si viene producto_id, filtrar por ID (autocompletado)
        if ($request->filled('producto_id')) {
            $query->where('productos.codigo_producto', (int) $request->producto_id);
        } else {
            // Búsqueda normal por texto
            if ($buscar = trim((string) $request->input('buscar'))) {
                $like = "%{$buscar}%";
                $query->where(function ($w) use ($like) {
                    $w->where('productos.nombre', 'like', $like)
                        ->orWhere('productos.numero_parte', 'like', $like)
                        ->orWhere('productos.categoria', 'like', $like)
                        ->orWhere('productos.clave_prodserv', 'like', $like);
                });
            }
        }

        if ($request->filled('categoria')) {
            $query->where('productos.categoria', $request->input('categoria'));
        }

        // ✅ Mantengo tu lógica: si NO marcas inactivos, solo activos; si marcas, solo inactivos
        if (!$request->boolean('inactivos')) {
            $query->where('productos.activo', true);
        } else {
            $query->where('productos.activo', false);
        }

        if ($request->boolean('stock_bajo')) {
            $query->whereRaw("$subStock <= COALESCE(productos.stock_seguridad,0)");
        }

        $productos  = $query->orderByDesc('productos.created_at')->paginate(12)->withQueryString();
        $categorias = Producto::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria');

        return view('vistas-gerente.productos-gerente.catalogo_producto_gerente', compact('productos', 'categorias'));
    }

    public function crear()
    {
        $base = ['hardware', 'software', 'perifericos', 'componentes', 'redes', 'accesorios', 'otra'];
        $fromDb = Producto::whereNotNull('categoria')->distinct()->pluck('categoria')->toArray();
        $categoriasPredefinidas = $base;
        $categoriasExtra = collect(array_merge($base, $fromDb))->unique()->diff($base)->sort()->values()->toArray();

        return view('vistas-gerente.productos-gerente.entrada_producto_gerente', compact('categoriasPredefinidas', 'categoriasExtra'));
    }

    public function guardar(Request $request)
    {
        // ✅ OJO: unidad ahora SIEMPRE se trimmea y NO se vuelve null
        $request->merge([
            'nombre'          => trim((string) $request->nombre),
            'numero_parte'    => $request->filled('numero_parte') ? strtoupper(trim((string) $request->numero_parte)) : null,
            'categoria'       => $request->filled('categoria') ? trim((string) $request->categoria) : null,
            'clave_prodserv'  => $request->filled('clave_prodserv') ? preg_replace('/\D+/', '', $request->clave_prodserv) : null,
            'unidad'          => trim((string) $request->unidad), // ✅ OBLIGATORIO (no null)
            'stock_seguridad' => $request->filled('stock_seguridad') ? (int) $request->stock_seguridad : 0,
            'descripcion'     => $request->filled('descripcion') ? trim((string) $request->descripcion) : null,
            'require_serie'   => $request->boolean('require_serie'),
        ]);

        $request->validate([
            'nombre'          => 'required|string|min:3|max:255',
            'numero_parte'    => 'nullable|string|max:100|unique:productos,numero_parte',
            'categoria'       => 'nullable|string|max:255',
            'clave_prodserv'  => ['nullable', 'regex:/^\d{4,8}$/'],
            'unidad'          => 'required|string|max:50', // ✅ YA NO nullable
            'stock_seguridad' => 'nullable|integer|min:0',
            'descripcion'     => 'nullable|string',
            'imagen'          => 'nullable|image|max:2048',
            'require_serie'   => 'boolean',
        ], [
            'unidad.required' => 'La unidad es obligatoria.',
        ]);

        // ✅ Autogenerar SKU si no viene numero_parte
        $autoMsg = null;
        $numeroParte = $request->numero_parte;

        if (!$numeroParte) {
            $numeroParte = 'SKU-' . strtoupper(Str::random(8));
            $autoMsg = 'No se capturó Número de Parte: se generó un SKU interno.';
        }

        $numeroParte = strtoupper($numeroParte);
        $numeroParte = $this->uniqueNumeroParte($numeroParte);

        $rutaImagen = null;
        if ($request->hasFile('imagen')) {
            $img = $request->file('imagen');
            $name = Str::uuid() . '.' . $img->getClientOriginalExtension();
            $img->move(public_path('imagenes_productos'), $name);
            $rutaImagen = 'imagenes_productos/' . $name;
        }

        Producto::create([
            'nombre'               => $request->nombre,
            'numero_parte'         => $numeroParte,
            'categoria'            => $request->categoria,
            'clave_prodserv'       => $request->clave_prodserv,
            'unidad'               => $request->unidad, // ✅ SIEMPRE llega
            'stock_seguridad'      => $request->stock_seguridad,
            'descripcion'          => $request->descripcion,
            'imagen'               => $rutaImagen,
            'activo'               => true,
            'require_serie'        => $request->require_serie,
            // compat
            'stock_total'          => 0,
            'stock_paquetes'       => 0,
            'stock_piezas_sueltas' => 0,
        ]);

        $msg = 'Producto guardado.';
        if ($autoMsg) $msg .= ' ' . $autoMsg . ' Puedes modificarlo después.';

        return redirect()->route('catalogo.index')->with('success', $msg);
    }

    public function editar($id)
    {
        $producto = Producto::findOrFail($id);

        $base = ['hardware', 'software', 'perifericos', 'componentes', 'redes', 'accesorios', 'otra'];
        $fromDb = Producto::whereNotNull('categoria')->distinct()->pluck('categoria')->toArray();
        $categoriasPredefinidas = $base;
        $categoriasExtra = collect(array_merge($base, $fromDb))->unique()->diff($base)->sort()->values()->toArray();

        return view('vistas-gerente.productos-gerente.editar_producto_gerente', compact('producto', 'categoriasPredefinidas', 'categoriasExtra'));
    }

    public function actualizar(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        // ✅ OJO: unidad ahora SIEMPRE se trimmea y NO se vuelve null
        $request->merge([
            'nombre'          => trim((string) $request->nombre),
            'numero_parte'    => $request->filled('numero_parte') ? strtoupper(trim((string) $request->numero_parte)) : null,
            'categoria'       => $request->filled('categoria') ? trim((string) $request->categoria) : null,
            'clave_prodserv'  => $request->filled('clave_prodserv') ? preg_replace('/\D+/', '', $request->clave_prodserv) : null,
            'unidad'          => trim((string) $request->unidad), // ✅ OBLIGATORIO
            'stock_seguridad' => $request->filled('stock_seguridad') ? (int) $request->stock_seguridad : 0,
            'descripcion'     => $request->filled('descripcion') ? trim((string) $request->descripcion) : null,
            'require_serie'   => $request->boolean('require_serie'),
        ]);

        $request->validate([
            'nombre'          => 'required|string|min:3|max:255',
            'numero_parte'    => ['required', 'string', 'max:100', Rule::unique('productos', 'numero_parte')->ignore($producto->codigo_producto, 'codigo_producto')],
            'categoria'       => 'nullable|string|max:255',
            'clave_prodserv'  => ['nullable', 'regex:/^\d{4,8}$/'],
            'unidad'          => 'required|string|max:50', // ✅ YA NO nullable
            'stock_seguridad' => 'nullable|integer|min:0',
            'descripcion'     => 'nullable|string',
            'imagen'          => 'nullable|image|max:2048',
            'require_serie'   => 'boolean',
        ], [
            'unidad.required' => 'La unidad es obligatoria.',
        ]);

        if ($request->hasFile('imagen')) {
            $img = $request->file('imagen');
            $name = Str::uuid() . '.' . $img->getClientOriginalExtension();
            $img->move(public_path('imagenes_productos'), $name);
            $producto->imagen = 'imagenes_productos/' . $name;
        }

        $producto->fill($request->only([
            'nombre',
            'numero_parte',
            'categoria',
            'clave_prodserv',
            'unidad',
            'stock_seguridad',
            'descripcion',
            'require_serie'
        ]));
        $producto->save();

        return redirect()->route('catalogo.index')->with('success', 'Producto actualizado.');
    }

    public function desactivar($id)
    {
        $p = Producto::findOrFail($id);

        $stock = DB::table('inventario')
            ->where('codigo_producto', $p->codigo_producto)
            ->selectRaw('COALESCE(SUM(paquetes_restantes * COALESCE(piezas_por_paquete,1) + COALESCE(piezas_sueltas,0)),0) as s')
            ->value('s');

        if ($stock > 0) {
            return back()->with('error', 'No puedes desactivar un producto con stock en inventario.');
        }

        $p->activo = false;
        $p->save();

        return back()->with('success', 'Producto desactivado.');
    }

    public function activar($id)
    {
        $p = Producto::findOrFail($id);
        $p->activo = true;
        $p->save();

        return back()->with('success', 'Producto activado.');
    }

    public function eliminar($id)
    {
        $p = Producto::findOrFail($id);

        if ($p->activo) {
            return back()->with('error', 'Desactiva el producto antes de eliminarlo.');
        }

        $stock = DB::table('inventario')
            ->where('codigo_producto', $p->codigo_producto)
            ->selectRaw('COALESCE(SUM(paquetes_restantes * COALESCE(piezas_por_paquete,1) + COALESCE(piezas_sueltas,0)),0) as s')
            ->value('s');

        if ($stock > 0) {
            return back()->with('error', 'No se puede eliminar: existen movimientos en inventario.');
        }

        $p->delete();

        return redirect()->route('catalogo.index')->with('success', 'Producto eliminado.');
    }

    // ✅ Autocomplete ahora devuelve {id, label}
    public function autocomplete(Request $request)
    {
        $term = (string) $request->input('term', $request->input('q', ''));
        $term = trim($term);

        if ($term === '') {
            return response()->json([]);
        }

        $like = "%{$term}%";

        $productos = Producto::query()
            ->where(function ($q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('numero_parte', 'like', $like);
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'    => $p->codigo_producto,
                'label' => $p->nombre . ($p->numero_parte ? " ({$p->numero_parte})" : ''),
            ]);

        return response()->json($productos);
    }

    /** Garantiza unicidad agregando sufijos -2, -3, ... si es necesario */
    private function uniqueNumeroParte(string $base): string
    {
        $candidate = $base;
        $i = 2;
        while (Producto::where('numero_parte', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 9999) break;
        }
        return $candidate;
    }
}
