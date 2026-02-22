<a href="{{ url()->previous() }}"
   class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white text-sm font-medium px-4 py-2 rounded-md shadow transition duration-150 ease-in-out">
    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
    {{ $slot ?? 'Volver' }}
</a>
