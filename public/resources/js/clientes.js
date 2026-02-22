document.addEventListener('DOMContentLoaded', function() {
    const clienteSelect = document.getElementById('cliente-select');
    const nuevoClienteForm = document.getElementById('nuevo-cliente-form');
    const nombreClienteInput = document.querySelector('input[name="nombre_cliente"]');
    const contactoClienteInput = document.querySelector('input[name="contacto_cliente"]');

    clienteSelect.addEventListener('change', function() {
        if (this.value === 'nuevo') {
            nuevoClienteForm.classList.remove('hidden');
            // Limpiar y hacer requeridos los campos del nuevo cliente

            // Opcional: deshabilitar los campos originales
            if (nombreClienteInput) nombreClienteInput.disabled = true;
            if (contactoClienteInput) contactoClienteInput.disabled = true;
        } else {
            nuevoClienteForm.classList.add('hidden');
            // Quitar requeridos de los campos del nuevo cliente
            document.querySelectorAll('#nuevo-cliente-form input').forEach(input => {
                input.required = false;
            });
            // Habilitar los campos originales
            if (nombreClienteInput) nombreClienteInput.disabled = false;
            if (contactoClienteInput) contactoClienteInput.disabled = false;

            // Opcional: si quieres cargar automáticamente el contacto del cliente seleccionado
            // Necesitarías tener los datos de contacto en un atributo data-* de cada option
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && contactoClienteInput) {
                contactoClienteInput.value = selectedOption.dataset.contacto || '';
            }
        }
    });
});
