<label for="{{ $name }}" class="block mb-2 text-sm font-medium text-gray-700">
    {{ $label }}
</label>
<select id="{{ $name }}" name="{{ $name }}" class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring focus:ring-indigo-200">
    <option value="">-- Selecciona una opción --</option>
    @foreach($options as $value => $text)
        <option value="{{ $value }}">{{ $text }}</option>
    @endforeach
</select>
