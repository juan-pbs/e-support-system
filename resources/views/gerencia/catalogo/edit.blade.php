@extends('layouts.sidebar-navigation')

@section('content')
    <div class="relative mb-10 text-center mx-a">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Editar Producto</h1>

        <div class="flex items-center justify-between mb-6">
            <x-boton-volver />
        </div>
    </div>

    <div class="max-w-7xl mx-auto">
        @if ($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="text-xs text-gray-600 mb-3">
            <span class="text-red-600 font-bold">*</span> Campos obligatorios
        </div>

        <form action="{{ route('producto.actualizar', $producto->codigo_producto) }}" method="POST" enctype="multipart/form-data"
              class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Nombre (OBLIGATORIO) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Nombre <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="nombre" value="{{ old('nombre', $producto->nombre) }}" required
                           class="w-full border px-3 py-2 rounded-lg @error('nombre') border-red-500 @enderror">
                </div>

                {{-- Número de parte (OBLIGATORIO) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Número de parte <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="numero_parte"
                           value="{{ old('numero_parte', $producto->numero_parte) }}"
                           class="w-full border px-3 py-2 rounded-lg @error('numero_parte') border-red-500 @enderror"
                           pattern="[A-Za-z0-9\-]+"
                           title="Solo letras, números y guiones"
                           required>
                    <p class="text-xs text-gray-500 mt-1">
                        Identificador único del producto. Puedes modificarlo si lo necesitas.
                    </p>
                </div>

                {{-- Unidad (OBLIGATORIO) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Unidad <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="unidad" value="{{ old('unidad', $producto->unidad) }}"
                           class="w-full border px-3 py-2 rounded-lg @error('unidad') border-red-500 @enderror"
                           placeholder="p. ej. pieza / PZA / caja"
                           required>
                    @error('unidad')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Clave SAT (opcional) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Clave Prod/Serv (opcional)</label>
                    <input type="text" name="clave_prodserv" value="{{ old('clave_prodserv', $producto->clave_prodserv) }}"
                           class="w-full border px-3 py-2 rounded-lg @error('clave_prodserv') border-red-500 @enderror">
                </div>

                {{-- Stock mínimo (opcional) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stock mínimo (opcional)</label>
                    <input type="number" name="stock_seguridad" value="{{ old('stock_seguridad', $producto->stock_seguridad ?? 0) }}"
                           class="w-full border px-3 py-2 rounded-lg @error('stock_seguridad') border-red-500 @enderror"
                           min="0" step="1">
                </div>

                {{-- Categoría (opcional) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Categoría (opcional)</label>
                    <select name="categoria" class="w-full border px-3 py-2 rounded-lg @error('categoria') border-red-500 @enderror">
                        <option value="">— Selecciona —</option>
                        @foreach($categoriasPredefinidas as $cat)
                            <option value="{{ $cat }}" {{ old('categoria', $producto->categoria)==$cat?'selected':'' }}>{{ ucfirst($cat) }}</option>
                        @endforeach
                        @foreach($categoriasExtra as $cat)
                            <option value="{{ $cat }}" {{ old('categoria', $producto->categoria)==$cat?'selected':'' }}>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Descripción (opcional) --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción (opcional)</label>
                    <textarea name="descripcion" rows="3"
                              class="w-full border px-3 py-2 rounded-lg @error('descripcion') border-red-500 @enderror">{{ old('descripcion', $producto->descripcion) }}</textarea>
                </div>

                {{-- Imagen (opcional) con fallback --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Imagen (opcional)</label>
                    <input type="file" name="imagen" accept="image/*"
                           class="w-full border px-3 py-2 rounded-lg @error('imagen') border-red-500 @enderror">
                    <div class="mt-2">
                        <img src="{{ $producto->imagen ? asset($producto->imagen) : asset('images/imagen.png') }}"
                             alt="Imagen actual"
                             class="h-28 object-contain bg-gray-50 rounded">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('catalogo.index') }}"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Actualizar
                </button>
            </div>
        </form>
    </div>
@endsection
