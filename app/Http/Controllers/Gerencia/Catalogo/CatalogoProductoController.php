<?php

namespace App\Http\Controllers\Gerencia\Catalogo;

use App\Http\Controllers\Controller;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Services\Ordenes\OrdenServicioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CatalogoProductoController extends Controller
{
    public function __construct(private OrdenServicioService $ordenService) {}

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
        $this->purgeExpiredTrash();
        $this->syncCategoriasDesdeProductos();
        $isSystemUser = $this->isSystemUser($request);
        $papelera = $isSystemUser && $request->boolean('papelera');

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
                DB::raw("$subStock AS stock_fisico"),
                DB::raw("$aggProv AS proveedores_str"),
            ]))
            ->groupBy($prodCols);

        if ($papelera) {
            $query->onlyTrashed();
        }

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
        if (! $papelera) {
            if (!$request->boolean('inactivos')) {
                $query->where('productos.activo', true);
            } else {
                $query->where('productos.activo', false);
            }
        }

        if ($request->boolean('stock_bajo')) {
            $query->whereRaw("$subStock <= COALESCE(productos.stock_seguridad,0)");
        }

        $perPage = $isSystemUser
            ? (int) $request->input('per_page', 12)
            : 12;

        if (! in_array($perPage, [12, 24, 48, 96], true)) {
            $perPage = 12;
        }

        $productos  = $query->orderByDesc('productos.created_at')->paginate($perPage)->withQueryString();
        $productos->getCollection()->transform(function ($producto) {
            $codigo = (int) ($producto->codigo_producto ?? 0);
            $disponible = 0;

            if ($codigo > 0) {
                try {
                    $disponible = $this->ordenService->calculateAvailableForProduct($codigo);
                } catch (\Throwable $e) {
                    $disponible = 0;
                }
            }

            $producto->stock_disponible = max((int) $disponible, 0);
            $producto->sin_disponible   = $producto->stock_disponible <= 0;

            return $producto;
        });
        $categorias = CategoriaProducto::query()
            ->orderBy('nombre')
            ->pluck('nombre');

        return view('gerencia.catalogo.index', compact('productos', 'categorias', 'papelera') + $this->catalogoCategoriasFormData());
    }

    public function crear()
    {
        $this->syncCategoriasDesdeProductos();

        return view('gerencia.catalogo.create', $this->catalogoCategoriasFormData());
    }

    public function guardar(Request $request)
    {
        // ✅ OJO: unidad ahora SIEMPRE se trimmea y NO se vuelve null
        $request->merge([
            'nombre'          => trim((string) $request->nombre),
            'numero_parte'    => $request->filled('numero_parte') ? $this->normalizeNumeroParte($request->numero_parte) : null,
            'categoria'       => $this->resolveCategoriaInput($request),
            'clave_prodserv'  => $request->filled('clave_prodserv') ? preg_replace('/\D+/', '', $request->clave_prodserv) : null,
            'unidad'          => trim((string) $request->unidad), // ✅ OBLIGATORIO (no null)
            'stock_seguridad' => $request->filled('stock_seguridad') ? (int) $request->stock_seguridad : 0,
            'descripcion'     => $request->filled('descripcion') ? trim((string) $request->descripcion) : null,
            'require_serie'   => $request->boolean('require_serie'),
            'redirect_to'     => $this->resolveRedirectTarget($request->input('redirect_to')),
        ]);

        $request->validate([
            'nombre'          => 'required|string|min:3|max:255',
            'numero_parte'    => 'nullable|string|max:100|unique:productos,numero_parte',
            'categoria'       => 'required|string|max:255',
            'categoria_nueva' => 'nullable|string|max:255',
            'clave_prodserv'  => ['nullable', 'regex:/^\d{4,8}$/'],
            'unidad'          => 'required|string|max:50', // ✅ YA NO nullable
            'stock_seguridad' => 'nullable|integer|min:0',
            'descripcion'     => 'nullable|string',
            'imagen'          => 'nullable|image|max:2048',
            'require_serie'   => 'boolean',
            'redirect_to'     => 'nullable|string|max:2048',
        ], [
            'categoria.required' => 'La categoría es obligatoria.',
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

        $this->ensureCategoriaExiste($request->categoria);

        $msg = 'Producto guardado.';
        if ($autoMsg) $msg .= ' ' . $autoMsg . ' Puedes modificarlo después.';

        if ($redirectTo = $this->resolveRedirectTarget($request->input('redirect_to'))) {
            return redirect()->to($redirectTo)->with('success', $msg);
        }

        return redirect()->route('catalogo.index')->with('success', $msg);
    }

    public function editar($id)
    {
        $producto = Producto::findOrFail($id);

        $this->syncCategoriasDesdeProductos();

        return view('gerencia.catalogo.edit', [
            'producto' => $producto,
        ] + $this->catalogoCategoriasFormData());
    }

    public function actualizar(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        // ✅ OJO: unidad ahora SIEMPRE se trimmea y NO se vuelve null
        $request->merge([
            'nombre'          => trim((string) $request->nombre),
            'numero_parte'    => $request->filled('numero_parte') ? $this->normalizeNumeroParte($request->numero_parte) : null,
            'categoria'       => $this->resolveCategoriaInput($request),
            'clave_prodserv'  => $request->filled('clave_prodserv') ? preg_replace('/\D+/', '', $request->clave_prodserv) : null,
            'unidad'          => trim((string) $request->unidad), // ✅ OBLIGATORIO
            'stock_seguridad' => $request->filled('stock_seguridad') ? (int) $request->stock_seguridad : 0,
            'descripcion'     => $request->filled('descripcion') ? trim((string) $request->descripcion) : null,
            'require_serie'   => $request->boolean('require_serie'),
            'redirect_to'     => $this->resolveRedirectTarget($request->input('redirect_to')),
        ]);

        $request->validate([
            'nombre'          => 'required|string|min:3|max:255',
            'numero_parte'    => ['required', 'string', 'max:100', Rule::unique('productos', 'numero_parte')->ignore($producto->codigo_producto, 'codigo_producto')],
            'categoria'       => 'required|string|max:255',
            'categoria_nueva' => 'nullable|string|max:255',
            'clave_prodserv'  => ['nullable', 'regex:/^\d{4,8}$/'],
            'unidad'          => 'required|string|max:50', // ✅ YA NO nullable
            'stock_seguridad' => 'nullable|integer|min:0',
            'descripcion'     => 'nullable|string',
            'imagen'          => 'nullable|image|max:2048',
            'require_serie'   => 'boolean',
            'redirect_to'     => 'nullable|string|max:2048',
        ], [
            'categoria.required' => 'La categoría es obligatoria.',
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

        $this->ensureCategoriaExiste($request->categoria);

        if ($redirectTo = $this->resolveRedirectTarget($request->input('redirect_to'))) {
            return redirect()->to($redirectTo)->with('success', 'Producto actualizado.');
        }

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

    public function eliminar(Request $request, $id)
    {
        abort_unless($this->isSystemUser($request), 403);

        $p = Producto::findOrFail($id);

        $p->activo = false;
        $p->deleted_by = auth()->id();
        $p->save();
        $p->delete();

        return redirect()->route('catalogo.index')->with('success', 'Producto enviado a papelera. Se puede recuperar durante 20 dias.');
    }

    public function restaurar(Request $request, $id)
    {
        abort_unless($this->isSystemUser($request), 403);

        $producto = Producto::onlyTrashed()->findOrFail($id);

        if ($producto->deleted_at && $producto->deleted_at->lt(now()->subDays(20))) {
            $producto->forceDelete();

            return redirect()->route('catalogo.index', ['papelera' => 1])
                ->with('error', 'El producto ya supero los 20 dias en papelera y fue eliminado permanentemente.');
        }

        $producto->restore();
        $producto->deleted_by = null;
        $producto->activo = true;
        $producto->save();

        return redirect()->route('catalogo.index', ['papelera' => 1])->with('success', 'Producto recuperado.');
    }

    public function bulkAction(Request $request)
    {
        abort_unless($this->isSystemUser($request), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['desactivar', 'eliminar', 'restaurar'])],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*' => ['integer'],
        ], [
            'productos.required' => 'Selecciona al menos un producto.',
        ]);

        $ids = collect($data['productos'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Selecciona al menos un producto.');
        }

        if ($data['action'] === 'restaurar') {
            $productos = Producto::onlyTrashed()->whereIn('codigo_producto', $ids)->get();
            $restaurados = 0;

            foreach ($productos as $producto) {
                if ($producto->deleted_at && $producto->deleted_at->lt(now()->subDays(20))) {
                    $producto->forceDelete();
                    continue;
                }

                $producto->restore();
                $producto->deleted_by = null;
                $producto->activo = true;
                $producto->save();
                $restaurados++;
            }

            return back()->with('success', "Productos recuperados: {$restaurados}.");
        }

        $productos = Producto::query()
            ->whereIn('codigo_producto', $ids)
            ->get();

        if ($data['action'] === 'desactivar') {
            Producto::whereIn('codigo_producto', $productos->pluck('codigo_producto'))->update(['activo' => false]);

            return back()->with('success', 'Productos desactivados: ' . $productos->count() . '.');
        }

        foreach ($productos as $producto) {
            $producto->activo = false;
            $producto->deleted_by = auth()->id();
            $producto->save();
            $producto->delete();
        }

        return back()->with('success', 'Productos enviados a papelera: ' . $productos->count() . '.');
    }

    public function guardarCategoria(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $nombre = $this->normalizeCategoria($request->input('nombre'));
        if ($nombre === null) {
            return back()->with('error', 'Captura un nombre de categoría válido.');
        }

        if ($this->buscarCategoriaPorNombre($nombre)) {
            return back()->with('error', 'La categoría ya existe.');
        }

        CategoriaProducto::create([
            'nombre' => $nombre,
        ]);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function actualizarCategoria(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $categoria = CategoriaProducto::findOrFail($id);
        $nuevoNombre = $this->normalizeCategoria($request->input('nombre'));

        if ($nuevoNombre === null) {
            return back()->with('error', 'Captura un nombre de categoría válido.');
        }

        $duplicada = $this->buscarCategoriaPorNombre($nuevoNombre);
        if ($duplicada && (int) $duplicada->id !== (int) $categoria->id) {
            return back()->with('error', 'Ya existe una categoría con ese nombre.');
        }

        DB::transaction(function () use ($categoria, $nuevoNombre) {
            $nombreAnterior = $categoria->nombre;

            $categoria->nombre = $nuevoNombre;
            $categoria->save();

            Producto::where('categoria', $nombreAnterior)->update([
                'categoria' => $nuevoNombre,
            ]);
        });

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function eliminarCategoria($id)
    {
        $categoria = CategoriaProducto::findOrFail($id);

        if (Producto::where('categoria', $categoria->nombre)->exists()) {
            return back()->with('error', 'No se puede eliminar la categoría porque ya tiene productos asignados.');
        }

        $categoria->delete();

        return back()->with('success', 'Categoría eliminada correctamente.');
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

    private function syncCategoriasDesdeProductos(): void
    {
        if (!Schema::hasTable('categorias_productos')) {
            return;
        }

        $categorias = Producto::query()
            ->whereNotNull('categoria')
            ->pluck('categoria')
            ->map(fn ($categoria) => $this->normalizeCategoria($categoria))
            ->filter()
            ->unique()
            ->values();

        foreach ($categorias as $categoria) {
            $this->ensureCategoriaExiste($categoria);
        }
    }

    private function purgeExpiredTrash(): void
    {
        if (! Schema::hasColumn('productos', 'deleted_at')) {
            return;
        }

        Producto::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(20))
            ->chunkById(100, function ($productos) {
                foreach ($productos as $producto) {
                    $producto->forceDelete();
                }
            }, 'codigo_producto');
    }

    private function isSystemUser(Request $request): bool
    {
        $user = $request->user();

        return $user && method_exists($user, 'isSystem') && $user->isSystem();
    }

    private function catalogoCategoriasFormData(): array
    {
        $registradas = CategoriaProducto::query()
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn ($categoria) => $this->normalizeCategoria($categoria))
            ->filter()
            ->unique(fn ($categoria) => mb_strtolower($categoria, 'UTF-8'))
            ->values();

        $categoriasBase = $this->categoriasBase();
        $categoriasBaseLc = array_map(
            fn ($categoria) => mb_strtolower((string) $categoria, 'UTF-8'),
            $categoriasBase
        );

        $categoriasExtra = $registradas
            ->filter(fn ($categoria) => !in_array(mb_strtolower($categoria, 'UTF-8'), $categoriasBaseLc, true))
            ->values();

        return [
            'categorias'             => $registradas,
            'categoriasPredefinidas' => $categoriasBase,
            'categoriasExtra'        => $categoriasExtra,
            'categoriasResumen'      => CategoriaProducto::query()
                ->orderBy('nombre')
                ->get()
                ->map(function (CategoriaProducto $categoria) {
                    $categoria->productos_count = Producto::where('categoria', $categoria->nombre)->count();
                    return $categoria;
                }),
        ];
    }

    private function categoriasBase(): array
    {
        return ['hardware', 'software', 'perifericos', 'componentes', 'redes', 'accesorios', 'otra'];
    }

    private function ensureCategoriaExiste(?string $categoria): void
    {
        $nombre = $this->normalizeCategoria($categoria);

        if ($nombre === null || !Schema::hasTable('categorias_productos')) {
            return;
        }

        $categoriaExistente = $this->buscarCategoriaPorNombre($nombre);

        if ($categoriaExistente) {
            if ($categoriaExistente->nombre !== $nombre) {
                $categoriaExistente->nombre = $nombre;
                $categoriaExistente->save();
            }

            return;
        }

        CategoriaProducto::create([
            'nombre' => $nombre,
        ]);
    }

    private function buscarCategoriaPorNombre(string $nombre): ?CategoriaProducto
    {
        return CategoriaProducto::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($nombre), 'UTF-8')])
            ->first();
    }

    private function normalizeCategoria(mixed $value): ?string
    {
        $categoria = trim((string) $value);
        $categoria = preg_replace('/\s+/', ' ', $categoria) ?? '';

        return $categoria !== '' ? $categoria : null;
    }

    private function resolveCategoriaInput(Request $request): ?string
    {
        $categoriaNueva = $this->normalizeCategoria($request->input('categoria_nueva'));
        if ($categoriaNueva !== null) {
            return $categoriaNueva;
        }

        return $this->normalizeCategoria($request->input('categoria'));
    }

    private function normalizeNumeroParte(mixed $value): string
    {
        $numeroParte = trim((string) $value);
        $numeroParte = preg_replace('/\s+/', '', $numeroParte) ?? '';

        return mb_strtoupper($numeroParte, 'UTF-8');
    }

    private function resolveRedirectTarget(?string $target): ?string
    {
        $target = trim((string) $target);
        if ($target === '') {
            return null;
        }

        $base = rtrim(url('/'), '/');

        if ($target === $base || str_starts_with($target, $base . '/')) {
            return $target;
        }

        return null;
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

    // Compat con rutas historicas.
    public function inactivos(Request $request)
    {
        $request->merge(['inactivos' => 1]);
        return $this->index($request);
    }

    public function plantilla()
    {
        return redirect()->route('catalogo.carga_rapida.plantilla');
    }

    public function exportar()
    {
        return redirect()->route('catalogo.index')
            ->with('error', 'La exportacion de catalogo no esta disponible en este controlador.');
    }
}
