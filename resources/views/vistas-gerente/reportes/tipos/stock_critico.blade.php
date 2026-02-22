{{-- resources/views/vistas-gerente/reportes/tipos/stock_critico.blade.php --}}

<label class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 border border-gray-200 cursor-pointer">
    <input
        type="radio"
        name="tipo"
        class="h-4 w-4 text-blue-500"
        value="stock_critico"
        x-model="f.tipo"
    >
    <div class="flex-1">
        <div class="font-medium text-gray-800">Productos</div>
        <div class="text-xs text-gray-500 mt-1">
            Lista de todos los productos con su stock actual y precio
            (precio tomado de la última entrada de inventario).
        </div>
    </div>
</label>
