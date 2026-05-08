<div id="categorias-manager" class="space-y-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">Administrar categorías</h2>
        <p class="mt-1 text-sm text-gray-500">
            Crea, renombra o elimina categorías. Si una categoría ya tiene productos, el sistema solo mostrará la alerta y no la borrará.
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
        <form method="POST" action="{{ route('catalogo.categorias.guardar') }}" class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
            @csrf
            <label class="block text-sm font-medium text-gray-700">Nueva categoría</label>
            <input type="text" name="nombre" class="w-full rounded-lg border px-3 py-2" placeholder="Ej. CCTV">
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Agregar categoría
            </button>
        </form>

        <div class="space-y-3">
            @forelse($categoriasResumen as $categoria)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <form method="POST" action="{{ route('catalogo.categorias.actualizar', $categoria->id) }}" class="flex-1 space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="text"
                                   name="nombre"
                                   value="{{ $categoria->nombre }}"
                                   class="w-full rounded-lg border px-3 py-2">
                            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-900">
                                Guardar cambio
                            </button>
                        </form>

                        <div class="flex flex-col gap-2 xl:min-w-[180px] xl:items-end">
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                {{ $categoria->productos_count }} producto{{ $categoria->productos_count === 1 ? '' : 's' }}
                            </span>

                            @if($categoria->productos_count > 0)
                                <button type="button"
                                        class="rounded-lg bg-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-200"
                                        onclick="window.alert('No se puede eliminar la categoría «{{ $categoria->nombre }}» porque ya tiene productos asignados.')">
                                    Eliminar
                                </button>
                            @else
                                <form method="POST" action="{{ route('catalogo.categorias.eliminar', $categoria->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                                            onclick="return window.confirm('Se eliminará la categoría «{{ $categoria->nombre }}».');">
                                        Eliminar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-500">
                    Aún no hay categorías registradas.
                </div>
            @endforelse
        </div>
    </div>
</div>
