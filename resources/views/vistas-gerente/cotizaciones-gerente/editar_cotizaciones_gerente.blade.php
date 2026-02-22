{{-- resources/views/vistas-gerente/cotizaciones-gerente/editar_cotizaciones_gerente.blade.php --}}
@extends('layouts.sidebar-navigation')

@section('title', 'Editar Cotización')

@section('content')
    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-sm p-8">
        <div class="relative mb-10 text-center mx-a">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Editar Cotización</h1>

            <div class="flex items-center justify-between mb-6">
                <x-boton-volver />
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-md">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-error mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-md">
                {{ session('error') }}
            </div>
        @endif
        @if(session('db_error'))
            <div id="alert-db-error" class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300">
                {{ session('db_error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                <p class="font-semibold mb-2">Por favor corrige los siguientes errores:</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('cotizaciones.actualizar', $cotizacion->id_cotizacion) }}" id="cotizacionForm">
            @csrf
            @method('PUT')

            {{-- Fuente de verdad: JSON de productos --}}
            <input type="hidden" name="productos_json" id="productos_json" value='{{ $productosJson }}'>
            <input type="hidden" name="impuestos" id="impuestos" value="{{ $cotizacion->iva ?? 0 }}">
            <input type="hidden" name="total" id="total" value="{{ $cotizacion->total ?? 0 }}">
            <input type="hidden" name="cantidad_escrita" id="cantidad_escrita" value="{{ $cotizacion->cantidad_escrita }}">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- LEFT: Campos principales --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de solicitud:</label>
                            <select name="tipo_solicitud" id="tipo_solicitud"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="venta"   {{ $cotizacion->tipo_solicitud == 'venta' ? 'selected' : '' }}>Venta</option>
                                <option value="hibrido" {{ $cotizacion->tipo_solicitud == 'hibrido' ? 'selected' : '' }}>Híbrido</option>
                                <option value="servicio"{{ $cotizacion->tipo_solicitud == 'servicio' ? 'selected' : '' }}>Servicio</option>
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Moneda:</label>
                                <div class="text-[11px] text-gray-500">
                                    <span id="tasaMXNUSD" class="hidden"></span>
                                    <span class="mx-1">•</span>
                                    <span id="tasaUSDMXN" class="hidden"></span>
                                </div>
                            </div>
                            <select name="moneda" id="moneda"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="MXN" {{ $cotizacion->moneda == 'MXN' ? 'selected' : '' }}>MXN</option>
                                <option value="USD" {{ $cotizacion->moneda == 'USD' ? 'selected' : '' }}>USD</option>
                            </select>
                        </div>
                    </div>

                    {{-- Cliente (autocomplete igual que en CREAR) --}}
                    @php
                        $clienteIdOld = old('cliente_id', $cotizacion->registro_cliente);
                        $clienteSelected = $clientes->firstWhere('clave_cliente', $clienteIdOld);
                        $clienteSelectedText = $clienteSelected
                            ? ($clienteSelected->nombre.' - '.$clienteSelected->correo_electronico)
                            : '';
                    @endphp

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Selecciona Cliente:</label>

                        {{-- ✅ valor REAL enviado --}}
                        <input type="hidden" name="cliente_id" id="cliente_id" value="{{ $clienteIdOld }}">

                        <div class="relative">
                            <input
                                type="text"
                                id="clienteSearch"
                                value="{{ $clienteSelectedText }}"
                                placeholder="Buscar por nombre o correo..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autocomplete="off"
                            />

                            <div
                                id="clienteResults"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-64 overflow-y-auto hidden"
                            ></div>

                            <div id="clienteLoading" class="mt-1 text-xs text-gray-500 hidden">Buscando...</div>
                        </div>

                        @error('cliente_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="text-sm text-gray-500 mt-1">
                            ¿El cliente no aparece?
                            <a href="{{ route('clientes.nuevo', ['redirect' => url()->full()]) }}" class="text-blue-600 underline">
                                Regístralo aquí
                            </a>.
                        </p>
                    </div>

                    {{-- Descripción general --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción:</label>
                        <textarea name="descripcion" rows="4" placeholder="Describe detalles de la cotización"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('descripcion', $cotizacion->descripcion) }}</textarea>
                    </div>

                    {{-- Servicio condicional --}}
                    <div id="servicioFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ in_array($cotizacion->tipo_solicitud, ['hibrido', 'servicio']) ? '' : 'hidden' }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Costo del servicio</label>
                            <input type="number" id="precio_servicio" name="precio_servicio" placeholder="Costo del servicio" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                   value="{{ old('precio_servicio', optional($cotizacion->servicio)->precio ?? 0) }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción del servicio</label>
                            <textarea name="descripcion_servicio" rows="4" placeholder="Describe brevemente el servicio"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md resize-none">{{ old('descripcion_servicio', optional($cotizacion->servicio)->descripcion ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Costo operativo + Vigencia --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Costo operativo:</label>
                            <input type="number" name="costo_operativo" id="costo_operativo" step="0.01"
                                   value="{{ old('costo_operativo', $cotizacion->costo_operativo) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha límite (vigencia):</label>
                            <input type="date" name="vigencia" id="vigencia"
                                   value="{{ old('vigencia', optional($cotizacion->vigencia)->format('Y-m-d')) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                    </div>

                    {{-- Firma digital --}}
                    <x-firma-digital
                        :firma="($firmaEmpresa ?? $firmaDefaultEmpresa ?? null)"
                        fieldBase64="firma_empresa"
                        fieldNombre="firma_emp_nombre"
                        fieldPuesto="firma_emp_puesto"
                        fieldEmpresa="firma_emp_empresa"
                        fieldSaveDefault="firma_guardar_default"
                    />
                </div>

                {{-- RIGHT: Productos + totales --}}
                <div class="space-y-6">
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-4">Productos</h3>
                            <div class="bg-yellow-50 rounded-lg p-4 space-y-3" id="productList"><!-- JS --></div>

                            @error('productos_json')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror

                            <button type="button" onclick="openProductModal()" class="mt-3 text-sm text-blue-600 hover:text-blue-800 font-medium">
                                + Agregar producto
                            </button>
                        </div>

                        <div class="space-y-3 pt-4 border-t border-gray-200">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal productos</span>
                                <span class="font-medium" id="subtotalProductos">$0 {{ $cotizacion->moneda }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Costo de servicio</span>
                                <span class="font-medium" id="subtotalServicio">$0 {{ $cotizacion->moneda }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Costo operativo</span>
                                <span class="font-medium" id="subtotalEnvio">$0 {{ $cotizacion->moneda }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-thin">
                                <span>Impuestos (16%):</span>
                                <span id="impuestos_text">$0 {{ $cotizacion->moneda }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold">
                                <span>Total final:</span>
                                <span id="totalFinal_text" class="text-right">$0 {{ $cotizacion->moneda }}</span>
                            </div>
                            <div class="flex gap-2 text-sm font-thin items-start">
                                <span class="shrink-0">Cantidad escrita:</span>
                                <span id="cantidad_escrita_text"
                                      class="flex-1 text-right break-words max-h-20 overflow-y-auto text-xs">
                                    {{ $cotizacion->cantidad_escrita }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-6">
                        <button type="button"
                                onclick="generarCotizacion()"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            Previsualizar PDF
                        </button>
                        <button type="button"
                                onclick="guardarCambios()"
                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Modal productos (sin cambios) --}}
    <div id="productModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="relative z-10 max-w-4xl mx-auto bg-white rounded-lg shadow-lg w-full max-h-screen overflow-y-auto">
            <div id="productView" class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6 space-y-3 sm:space-y-0">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800">Agregar producto</h2>
                    <div class="flex items-center justify-between sm:justify-end gap-4">
                        <button id="addNonExistentProduct" class="text-green-600 hover:text-green-700 text-sm font-medium">
                            Agregar producto no existente
                        </button>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="mb-4 sm:mb-6">
                    <x-barra-busqueda-live
                        :action="route('cotizaciones.crear')"
                        autocompleteUrl="{{ route('productos.autocomplete') }}"
                        placeholder="Buscar productos..."
                        inputId="productSearch"
                        resultId="productSearchResults"
                        name="buscar"
                    />
                </div>

                <div class="w-full sm:w-48 mb-4 sm:mb-6">
                    <select id="productCategory" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                        <option value="">Todas las categorías</option>
                        @foreach($productosDisponibles->pluck('categoria')->unique() as $categoria)
                            <option value="{{ $categoria }}">{{ $categoria }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hidden lg:block overflow-x-auto max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                    <table class="w-full">
                        <thead class="bg-gray-50 sticky top-0">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Imagen</th>
                            <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Nombre</th>
                            <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Unidad</th>
                            <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Precio unitario</th>
                            <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Acción</th>
                        </tr>
                        </thead>
                        <tbody id="productTableBody">
                        @foreach($productosDisponibles as $producto)
                            <tr class="border-b border-gray-100 hover:bg-gray-50"
                                data-name="{{ strtolower($producto->nombre) }}"
                                data-category="{{ strtolower($producto->categoria) }}">
                                <td class="py-3 px-2">
                                    <div class="w-12 h-12 bg-blue-100 flex items-center justify-center rounded-full overflow-hidden">
                                        @if($producto->imagen)
                                            <img src="{{ \Illuminate\Support\Str::startsWith($producto->imagen, ['http://', 'https://']) ? $producto->imagen : asset($producto->imagen) }}"
                                                 alt="{{ $producto->nombre }}"
                                                 class="w-full h-full object-cover">
                                        @else
                                            <span class="text-blue-600 font-semibold">{{ substr($producto->nombre, 0, 1) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-2 text-sm text-gray-800 break-words max-w-[180px]">{{ $producto->nombre }}</td>
                                <td class="py-3 px-2 text-sm text-gray-600 break-words max-w-[120px]">{{ $producto->unidad }}</td>
                                <td class="py-3 px-2 text-sm text-gray-600">
                                    ${{ number_format(optional($producto->inventario->first())->precio ?? 0, 2) }}
                                </td>
                                <td class="py-3 px-2">
                                    <button onclick="openQuantityModal({{ $producto->codigo_producto }})"
                                            class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white hover:bg-green-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m-6 0H6"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="lg:hidden space-y-4 max-h-96 overflow-y-auto" id="mobileProductList">
                    @foreach($productosDisponibles as $producto)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                             data-name="{{ strtolower($producto->nombre) }}"
                             data-category="{{ strtolower($producto->categoria) }}">
                            <div class="flex items-start space-x-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center overflow-hidden">
                                    @if($producto->imagen)
                                        <img src="{{ \Illuminate\Support\Str::startsWith($producto->imagen, ['http://', 'https://']) ? $producto->imagen : asset($producto->imagen) }}"
                                             alt="{{ $producto->nombre }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <span class="text-blue-600 font-semibold">{{ substr($producto->nombre, 0, 1) }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-2 gap-2">
                                        <h3 class="text-sm font-medium text-gray-900 break-words">{{ $producto->nombre }}</h3>
                                        <button onclick="openQuantityModal({{ $producto->codigo_producto }})"
                                                class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white hover:bg-green-600 flex-shrink-0 ml-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m-6 0H6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-600 mb-2 break-words">{{ $producto->unidad }}</p>
                                    <div class="text-xs">
                                        <span class="text-gray-900 font-medium">
                                            ${{ number_format(optional($producto->inventario->first())->precio ?? 0, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Alta rápida --}}
            <div id="formView" class="p-4 sm:p-6 hidden">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6 space-y-3 sm:space-y-0">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800">Agregar Producto Nuevo</h2>
                    <button id="backToProducts" class="self-end sm:self-auto text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="nonExistentProductForm" class="space-y-4 sm:space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre del producto</label>
                        <input type="text" id="productName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm" placeholder="Nombre del producto" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Precio Unitario</label>
                            <input type="number" id="productPrice" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm" placeholder="0.00" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad</label>
                            <input type="number" id="productQuantity" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm" required>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="button" id="formAddProduct" class="w-full sm:w-auto bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-md font-medium text-sm">
                            Agregar
                        </button>
                        <button type="button" id="cancelAddProduct" class="ml-3 text-gray-500 hover:text-gray-700 text-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal cantidad --}}
    <div id="quantityModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="relative z-10 bg-white rounded-lg p-6 max-w-sm w-full mx-4">
            <div class="flex justify-between items-start mb-4 gap-3">
                <h3 id="productModalTitle"
                    class="text-sm md:text-base font-semibold text-gray-800 leading-snug break-words max-h-24 overflow-y-auto pr-2"></h3>
                <button onclick="closeQuantityModal()" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="modal-body"><!-- JS inject --></div>

            <div id="quantityError" class="text-red-500 text-sm mb-4 hidden">
                La cantidad debe ser mayor que 0 y el precio debe ser válido
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeQuantityModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancelar
                </button>
                <button type="button" onclick="addSelectedProduct()"
                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                    Agregar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal editar producto --}}
    <div id="editProductModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="relative z-10 bg-white rounded-lg p-6 max-w-sm w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Editar producto</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2 break-words" id="editProductName"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad:</label>
                <input type="number" id="editProductQuantity" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Precio unitario:</label>
                <input type="number" id="editProductPrice" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div id="editQuantityError" class="text-red-500 text-sm mb-4 hidden">
                La cantidad debe ser mayor que 0 y el precio debe ser válido
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancelar
                </button>
                <button type="button" onclick="updateProduct()" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                    Actualizar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal preview PDF --}}
    <div id="pdfPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-4 rounded-lg max-w-4xl w-full h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Previsualizar Cotización</h2>
                <button onclick="cerrarModalPDF()" class="text-gray-600 hover:text-black text-xl">&times;</button>
            </div>

            <iframe id="iframePDF" class="flex-1 border w-full overflow-auto"></iframe>

            <div class="flex justify-end mt-4">
                <button onclick="guardarCambios()"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">
                    Guardar Cambios
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // ===== Estado =====
    let products = [];
    let currentProduct = null;
    let currentEditIndex = null;

    /**
    * 🔒 Bloquea el submit automático cuando se editan productos
    */
    let bloqueandoSubmit = false;

    // Snapshots
    let preciosMXN = [];
    let precioServicioMXN = {{ optional($cotizacion->servicio)->precio ?? 0 }};
    let costoOperativoMXN = {{ $cotizacion->costo_operativo ?? 0 }};

    // Tipo de cambio
    let exchangeRates = { mxn_usd: 0.059, usd_mxn: 1 / 0.059 };

    const monedaSelect   = document.getElementById('moneda');
    const tipoSolicitud  = document.getElementById('tipo_solicitud');
    const servicioFields = document.getElementById('servicioFields');
    const costoOperativoInput = document.getElementById('costo_operativo');
    const productosJsonInput  = document.getElementById('productos_json');

    const availableProducts = {
        @foreach($productosDisponibles as $producto)
            {{ $producto->codigo_producto }}: {
                id: {{ $producto->codigo_producto }},
                nombre: "{{ addslashes($producto->nombre) }}",
                unidad: "{{ addslashes($producto->unidad) }}",
                precio: {{ optional($producto->inventario->first())->precio ?? 0 }},
                imagen: "{{ $producto->imagen ? addslashes(\Illuminate\Support\Str::startsWith($producto->imagen, ['http://', 'https://']) ? $producto->imagen : asset($producto->imagen)) : '' }}",
                categoria: "{{ addslashes($producto->categoria) }}",
                descripcion: "{{ addslashes($producto->descripcion ?? '') }}"
            },
        @endforeach
    };

    function actualizarLabelsTasa() {
        const mxnUsd = document.getElementById('tasaMXNUSD');
        const usdMxn = document.getElementById('tasaUSDMXN');
        if (!mxnUsd || !usdMxn) return;

        mxnUsd.textContent = `1 MXN = ${exchangeRates.mxn_usd.toFixed(4)} USD`;
        usdMxn.textContent = `1 USD = ${exchangeRates.usd_mxn.toFixed(4)} MXN`;

        mxnUsd.classList.remove('hidden');
        usdMxn.classList.remove('hidden');
    }

    async function cargarExchangeRate() {
        try {
            const res = await fetch("{{ route('api.tipo-cambio') }}", { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return actualizarLabelsTasa();

            const data = await res.json();
            if (data.ok && data.mxn_usd) {
                const v = parseFloat(data.mxn_usd);
                if (!isNaN(v) && v > 0) {
                    exchangeRates.mxn_usd = v;
                    exchangeRates.usd_mxn = data.usd_mxn ? parseFloat(data.usd_mxn) : (1 / v);
                }
            }
        } catch (e) {
            console.error(e);
        } finally {
            actualizarLabelsTasa();
        }
    }

    // ===== Autocomplete Cliente =====
    function initClienteAutocomplete() {
        const input   = document.getElementById('clienteSearch');
        const hidden  = document.getElementById('cliente_id');
        const box     = document.getElementById('clienteResults');
        const loading = document.getElementById('clienteLoading');

        if (!input || !hidden || !box) return;

        const url = @json(route('clientes.autocomplete.select'));
        const urlNuevo = @json(route('clientes.nuevo', ['redirect' => url()->full()]));
        let t = null;

        function closeBox() {
            box.classList.add('hidden');
            box.innerHTML = '';
        }
        function showBox(html) {
            box.innerHTML = html;
            box.classList.remove('hidden');
        }

        async function search() {
            const q = (input.value || '').trim();

            if (q.length >= 1) hidden.value = '';

            if (q.length < 2) {
                loading?.classList.add('hidden');
                closeBox();
                return;
            }

            loading?.classList.remove('hidden');

            try {
                const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                const items = res.ok ? await res.json() : [];

                let html = '';
                if (items.length) {
                    html += items.map(it => `
                        <button type="button"
                            class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm"
                            data-id="${it.id}"
                            data-text="${String(it.text || '').replaceAll('"','&quot;')}"
                        >${it.text}</button>
                    `).join('');
                } else {
                    html += `<div class="px-3 py-2 text-xs text-gray-500">Sin resultados</div>`;
                }

                html += `
                    <div class="border-t border-gray-100"></div>
                    <button type="button"
                        class="w-full text-left px-3 py-2 hover:bg-green-50 text-sm text-green-700 font-medium"
                        data-nuevo="1"
                    >➕ Registrar nuevo cliente...</button>
                `;

                showBox(html);
            } catch (e) {
                console.error(e);
                closeBox();
            } finally {
                loading?.classList.add('hidden');
            }
        }

        input.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(search, 250);
        });

        document.addEventListener('click', (e) => {
            if (!box.contains(e.target) && e.target !== input) closeBox();
        });

        box.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            if (btn.dataset.nuevo === '1') {
                window.location.href = urlNuevo;
                return;
            }

            hidden.value = btn.dataset.id;
            input.value = btn.dataset.text;
            closeBox();
        });
    }

    // ===== Init =====
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const raw = productosJsonInput.value;
            products = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(products)) products = [];
        } catch (e) { products = []; }

        tipoSolicitud.addEventListener('change', function() {
            if (this.value === 'hibrido' || this.value === 'servicio') {
                servicioFields.classList.remove('hidden');
                if (!document.getElementById('precio_servicio').value) document.getElementById('precio_servicio').value = '0';
            } else {
                servicioFields.classList.add('hidden');
            }
            updateTotals();
        });

        [costoOperativoInput, monedaSelect].forEach(el => el.addEventListener('input', updateTotals));
        document.getElementById('precio_servicio')?.addEventListener('input', updateTotals);

        initClienteAutocomplete();

        document.getElementById('addNonExistentProduct')?.addEventListener('click', e => { e.preventDefault(); showFormView(); });
        document.getElementById('backToProducts')?.addEventListener('click', e => { e.preventDefault(); showProductView(); });
        document.getElementById('formAddProduct')?.addEventListener('click', addNonExistentProduct);
        document.getElementById('cancelAddProduct')?.addEventListener('click', showProductView);

        document.getElementById('productSearch')?.addEventListener('input', filterProducts);
        document.getElementById('productCategory')?.addEventListener('change', filterProducts);

        actualizarLabelsTasa();
        cargarExchangeRate();

        updateProductList();
        updateTotals();

        const dbAlert = document.getElementById('alert-db-error');
        if (dbAlert) {
            setTimeout(() => {
                dbAlert.style.opacity = '0';
                setTimeout(() => dbAlert.remove(), 500);
            }, 5000);
        }
    });

    // ===== Modal helpers productos =====
    const productModal   = document.getElementById('productModal');
    const quantityModal  = document.getElementById('quantityModal');
    const editProductModal = document.getElementById('editProductModal');
    const productView    = document.getElementById('productView');
    const formView       = document.getElementById('formView');

    function showFormView() { productView.classList.add('hidden'); formView.classList.remove('hidden'); }
    function showProductView() { formView.classList.add('hidden'); productView.classList.remove('hidden'); }
    function openProductModal() { productModal.classList.remove('hidden'); showProductView(); }
    function closeModal() { productModal.classList.add('hidden'); }

    function openQuantityModal(productId) {
        currentProduct = availableProducts[productId];
        const moneda = monedaSelect.value;
        const basePriceMXN = Number(currentProduct?.precio || 0);
        const defaultPrice = moneda === 'USD'
            ? (basePriceMXN * exchangeRates.mxn_usd).toFixed(2)
            : basePriceMXN.toFixed(2);

        const modalBody = document.querySelector('#quantityModal .modal-body');
        modalBody.innerHTML = `
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad:</label>
                <input type="number" id="productQuantityInput" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Precio unitario (${moneda}):</label>
                <input type="number" id="productPriceInput" step="0.01" min="0" value="${defaultPrice}" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>`;
        document.getElementById('productModalTitle').textContent = currentProduct?.nombre || 'Producto';
        quantityModal.classList.remove('hidden');
    }
    function closeQuantityModal() { quantityModal.classList.add('hidden'); currentProduct = null; }

    function filterProducts() {
        const search = (document.getElementById('productSearch')?.value || '').toLowerCase();
        const theCategory = (document.getElementById('productCategory')?.value || '').toLowerCase();

        document.querySelectorAll('#productTableBody tr').forEach(row => {
            const name = row.dataset.name || '';
            const cat = row.dataset.category || '';
            row.style.display = (name.includes(search) && (theCategory === '' || cat.includes(theCategory))) ? '' : 'none';
        });

        document.querySelectorAll('#mobileProductList > div').forEach(card => {
            const name = card.dataset.name || '';
            const cat = card.dataset.category || '';
            card.style.display = (name.includes(search) && (theCategory === '' || cat.includes(theCategory))) ? '' : 'none';
        });
    }

    function addSelectedProduct() {
        const quantity = parseInt(document.getElementById('productQuantityInput').value);
        const thePrice = parseFloat(document.getElementById('productPriceInput').value);
        const error = document.getElementById('quantityError');

        if (quantity <= 0 || isNaN(thePrice) || thePrice < 0) { error.classList.remove('hidden'); return; }
        error.classList.add('hidden');

        addProductToQuote({
            id: currentProduct.id,
            name: currentProduct.nombre,
            price: thePrice,
            quantity: quantity,
            unit: currentProduct.unidad || 'unidad',
            image: currentProduct.imagen || '',
            description: currentProduct.descripcion || ''
        });

        closeQuantityModal();
    }

    function addNonExistentProduct() {
        const name = document.getElementById('productName').value.trim();
        const price = parseFloat(document.getElementById('productPrice').value);
        const quantity = parseInt(document.getElementById('productQuantity').value);

        if (name && !isNaN(price) && price >= 0 && !isNaN(quantity) && quantity > 0) {
            addProductToQuote({
                id: 'custom-' + Date.now(),
                name: name,
                price: price,
                quantity: quantity,
                unit: 'unidad',
                image: '',
                description: 'Producto personalizado'
            });

            document.getElementById('productName').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productQuantity').value = 1;

            showProductView();
        } else {
            alert('Completa correctamente los campos del producto.');
        }
    }

    function addProductToQuote(product) {
        if (product.image && !product.image.startsWith('http') && !product.image.startsWith('/')) product.image = '/' + product.image;
        const idx = products.findIndex(p => p.id === product.id);
        if (idx >= 0) products[idx].quantity += product.quantity;
        else products.push(product);

        updateProductList();
        updateTotals();
    }

    function updateProductList() {
        const productList = document.getElementById('productList');
        productList.innerHTML = '';

        if (products.length === 0) {
            productList.innerHTML = '<p class="text-sm text-gray-500">No hay productos agregados</p>';
            productosJsonInput.value = '[]';
            return;
        }

        products.forEach((p, i) => {
            const div = document.createElement('div');
            div.className = 'flex flex-wrap md:flex-nowrap justify-between items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg';
            div.innerHTML = `
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden">
                        ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover">`
                                  : `<span class="text-gray-500 font-medium">${(p.name || '').charAt(0)}</span>`}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium break-words">${p.name || ''}</p>
                        <p class="text-xs text-gray-500 break-words">${p.quantity} ${p.unit || 'unidad'} × $${Number(p.price).toFixed(2)}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-sm font-medium whitespace-nowrap">
                        $${(p.quantity * p.price).toFixed(2)}
                    </span>

                    <button type="button"
                            onclick="editProduct(${i})"
                            class="text-blue-600 hover:text-blue-800"
                            title="Editar">
                        ✏️
                    </button>

                    <button type="button"
                            onclick="removeProduct(${i})"
                            class="text-red-600 hover:text-red-800"
                            title="Eliminar">
                        🗑️
                    </button>
                </div>`;

            productList.appendChild(div);
        });

        productosJsonInput.value = JSON.stringify(products);
    }

    function removeProduct(index) { products.splice(index, 1); updateProductList(); updateTotals(); }
    function editProduct(index) {
        bloqueandoSubmit = true; // 🔒 bloquear submit
        currentEditIndex = index;

        const p = products[index];
        document.getElementById('editProductName').textContent = p.name || '';
        document.getElementById('editProductQuantity').value = p.quantity;
        document.getElementById('editProductPrice').value = Number(p.price).toFixed(2);

        editProductModal.classList.remove('hidden');
    }

    function closeEditModal() {
        editProductModal.classList.add('hidden');
        currentEditIndex = null;
        bloqueandoSubmit = false; // 🔓 liberar submit
    }
    function updateProduct() {
        const q = parseInt(document.getElementById('editProductQuantity').value);
        const pr = parseFloat(document.getElementById('editProductPrice').value);
        if (q <= 0 || isNaN(pr) || pr < 0) { document.getElementById('editQuantityError').classList.remove('hidden'); return; }
        document.getElementById('editQuantityError').classList.add('hidden');
        products[currentEditIndex].quantity = q;
        products[currentEditIndex].price = pr;
        updateProductList();
        updateTotals();
        closeEditModal();
    }

    function updateTotals() {
        const subtotal = products.reduce((t, p) => t + (Number(p.quantity) * Number(p.price)), 0);
        const servicio = (tipoSolicitud.value === 'hibrido' || tipoSolicitud.value === 'servicio')
            ? (parseFloat(document.getElementById('precio_servicio').value) || 0) : 0;
        const envio = parseFloat(costoOperativoInput.value) || 0;
        const moneda = monedaSelect.value;

        let impuestos = 0;
        if (tipoSolicitud.value !== 'servicio' || products.length > 0) {
            impuestos = (subtotal + (tipoSolicitud.value === 'hibrido' ? servicio : 0)) * 0.16;
        }

        const total = subtotal + servicio + envio + impuestos;

        document.getElementById('subtotalProductos').textContent = `$${subtotal.toFixed(2)} ${moneda}`;
        document.getElementById('subtotalServicio').textContent   = `$${servicio.toFixed(2)} ${moneda}`;
        document.getElementById('subtotalEnvio').textContent      = `$${envio.toFixed(2)} ${moneda}`;
        document.getElementById('impuestos_text').textContent     = `$${impuestos.toFixed(2)} ${moneda}`;
        document.getElementById('totalFinal_text').textContent    = `$${total.toFixed(2)} ${moneda}`;

        document.getElementById('impuestos').value = impuestos.toFixed(2);
        document.getElementById('total').value     = total.toFixed(2);

        const cantidadEscrita = numeroALetras(total, moneda);
        document.getElementById('cantidad_escrita').value = cantidadEscrita;
        document.getElementById('cantidad_escrita_text').textContent = cantidadEscrita;
    }

    function numeroALetras(num, moneda) {
        return (num === 0 ? 'Cero' : num.toFixed(2) + (moneda === 'USD' ? ' dólares' : ' pesos')) + ' M.N.';
    }

    // Cambio de moneda con snapshot (igual que en crear)
    monedaSelect.addEventListener('change', () => {
        const moneda = monedaSelect.value;
        const tasa = exchangeRates.mxn_usd;
        const tasaBack = exchangeRates.usd_mxn || (tasa > 0 ? 1 / tasa : 0);

        if (moneda === 'USD') {
            if (preciosMXN.length === 0) {
                preciosMXN = products.map(p => Number(p.price));
                precioServicioMXN = parseFloat(document.getElementById('precio_servicio').value) || 0;
                costoOperativoMXN = parseFloat(document.getElementById('costo_operativo').value) || 0;
            }

            products.forEach(p => { p.price = +(Number(p.price) * tasa).toFixed(2); });

            const ps = parseFloat(document.getElementById('precio_servicio').value) || 0;
            document.getElementById('precio_servicio').value = +((ps || precioServicioMXN) * tasa).toFixed(2);

            const co = parseFloat(document.getElementById('costo_operativo').value) || 0;
            document.getElementById('costo_operativo').value = +((co || costoOperativoMXN) * tasa).toFixed(2);
        } else {
            if (preciosMXN.length > 0) {
                products.forEach((p,i) => { if (typeof preciosMXN[i] !== 'undefined') p.price = preciosMXN[i]; });
                document.getElementById('precio_servicio').value = Number(precioServicioMXN || 0).toFixed(2);
                document.getElementById('costo_operativo').value = Number(costoOperativoMXN || 0).toFixed(2);
            } else {
                products.forEach(p => { p.price = +(Number(p.price) * tasaBack).toFixed(2); });
                const ps = parseFloat(document.getElementById('precio_servicio').value) || 0;
                document.getElementById('precio_servicio').value = +(ps * tasaBack).toFixed(2);
                const co = parseFloat(document.getElementById('costo_operativo').value) || 0;
                document.getElementById('costo_operativo').value = +(co * tasaBack).toFixed(2);
            }
        }

        updateProductList();
        updateTotals();
    });

    // Preview + Guardado
    function generarCotizacion() {
        const clienteId = document.getElementById('cliente_id')?.value;
        if (!clienteId) { alert('Selecciona un cliente de la lista.'); return; }

        const tipo = tipoSolicitud.value;
        if (tipo !== 'servicio' && products.length === 0) {
            alert('Debes agregar al menos un producto para este tipo de cotización.');
            return;
        }

        const form = document.getElementById('cotizacionForm');
        const formData = new FormData(form);

        // IMPORTANTE: en preview quitamos _method=PUT
        formData.delete('_method');

        fetch("{{ route('cotizaciones.preview') }}", {
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/pdf',
            },
            body: formData
        })
        .then(async (res) => {
            const contentType = res.headers.get('Content-Type') || '';

            if (res.ok && contentType.includes('application/pdf')) {
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                document.getElementById('iframePDF').src = url;
                document.getElementById('pdfPreviewModal').classList.remove('hidden');
                return;
            }

            const text = await res.text();
            alert('Error al generar el PDF.\nHTTP ' + res.status + ' ' + res.statusText + '\n\n' + text.substring(0, 500));
        })
        .catch(error => {
            alert('Error al generar el PDF (JS): ' + error.message);
            console.error(error);
        });
    }

    function cerrarModalPDF() { document.getElementById('pdfPreviewModal').classList.add('hidden'); }

function guardarCambios() {
    if (bloqueandoSubmit) {
        console.warn('Submit bloqueado: edición de producto activa');
        return;
    }

    const clienteId = document.getElementById('cliente_id')?.value;
    if (!clienteId) {
        alert('Selecciona un cliente de la lista.');
        return;
    }

    const tipo = tipoSolicitud.value;
    if (tipo !== 'servicio' && products.length === 0) {
        alert('Debes agregar al menos un producto para este tipo de cotización.');
        return;
    }

    document.getElementById('cotizacionForm').submit();
}


    window.openProductModal   = openProductModal;
    window.closeModal         = closeModal;
    window.openQuantityModal  = openQuantityModal;
    window.closeQuantityModal = closeQuantityModal;
    window.addSelectedProduct = addSelectedProduct;
    window.addNonExistentProduct = addNonExistentProduct;
    window.editProduct        = editProduct;
    window.updateProduct      = updateProduct;
    window.removeProduct      = removeProduct;

    window.generarCotizacion  = generarCotizacion;
    window.cerrarModalPDF     = cerrarModalPDF;
    window.guardarCambios     = guardarCambios;
    /**
 * 🚫 Evita que ENTER envíe el formulario mientras se edita un producto
 */
document.addEventListener('keydown', function (e) {
    if (bloqueandoSubmit && e.key === 'Enter') {
        e.preventDefault();
        return false;
    }
});

</script>
@endpush
