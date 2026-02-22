
    let products = [];
    const tipoSolicitud = document.getElementById('tipo_solicitud');
    const servicioFields = document.getElementById('servicioFields');
    const toggleEnvio = document.getElementById('toggleEnvio');
    const envioFields = document.getElementById('envioFields');
    const costoEnvioInput = document.getElementById('costo_envio');
    const costoServicioInput = document.getElementById('precio_servicio');
    const monedaSelect = document.getElementById('moneda');

    // Función para mostrar el formulario
        function showForm() {
            productView.classList.add('hidden');
            formView.classList.remove('hidden');
        }


    // Inicializar/ocultar los campos de servicio
    tipoSolicitud.addEventListener('change', function () {
        if (this.value === 'hibrido' || this.value === 'servicio') {
            servicioFields.classList.remove('hidden');
        } else {
            servicioFields.classList.add('hidden');
        }
        updateTotals();
    });


    // Inicializar/ocultar los campos de envío
    toggleEnvio.addEventListener('change', function () {
        if (this.checked) {
            envioFields.classList.remove('hidden');
        } else {
            envioFields.classList.add('hidden');
        }
        updateTotals();
    });

    // Actualizar los totales al cambiar los campos de costo
    costoEnvioInput.addEventListener('input', updateTotals);
    costoServicioInput.addEventListener('input', updateTotals);
    monedaSelect.addEventListener('change', updateTotals);

    // Funcion para manejar la lista de productos
    function updateProductList() {
    const productList = document.getElementById('productList');
    productList.innerHTML = '';

    products.forEach((product, index) => {
        const total = product.quantity * product.price;
        const div = document.createElement('div');
        div.className = 'flex justify-between items-center text-sm';
        div.innerHTML = `
            <span>${product.name}</span>
            <span>x${product.quantity}</span>
            <span>$${total.toFixed(2)} ${monedaSelect.value}</span>
            <button type="button" onclick="removeProduct(${index})" class="text-red-500 ml-2">Eliminar</button>
        `;
        productList.appendChild(div);
    });

    // 🔑 Actualizar campo oculto con productos en JSON
    document.getElementById('productos_json').value = JSON.stringify(products);

    updateTotals();
}




    // Actualizar los totales de la cotización
    function updateTotals() {
        const subtotalProductos = products.reduce((sum, p) => sum + (p.quantity * p.price), 0);
        const costoServicio = parseFloat(costoServicioInput.value) || 0;
        const costoEnvio = toggleEnvio.checked ? (parseFloat(costoEnvioInput.value) || 0) : 0;

        const baseImponible = subtotalProductos + costoServicio;
        const impuestos = baseImponible * 0.16; // IVA 16%
        const total = baseImponible + impuestos + costoEnvio;
        const moneda = monedaSelect.value;

        document.getElementById('subtotalProductos').textContent = `$${subtotalProductos.toFixed(2)} ${moneda}`;
        document.getElementById('subtotalServicio').textContent = `$${costoServicio.toFixed(2)} ${moneda}`;
        document.getElementById('subtotalEnvio').textContent = `$${costoEnvio.toFixed(2)} ${moneda}`;
        document.getElementById('impuestos_text').textContent = `$${impuestos.toFixed(2)} ${moneda}`;
        document.getElementById('totalFinal_text').textContent = `$${total.toFixed(2)} ${moneda}`;
        document.getElementById('impuestos').value = impuestos.toFixed(2);
        document.getElementById('total').value = total.toFixed(2);
        document.getElementById('cantidad_escrita').value = numeroATexto(total);



        document.getElementById('impuestos').value = impuestos.toFixed(2);
        document.getElementById('total').value = total.toFixed(2);
    }

    // Funciones para manejar el modal de productos
    function addProduct() {
        document.getElementById('productModal').classList.remove('hidden');
        document.getElementById('productModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('productModal').classList.add('hidden');
        document.getElementById('productModal').classList.remove('flex');
        document.getElementById('productName').value = '';
        document.getElementById('productQuantity').value = '1';
        document.getElementById('productPrice').value = '';
    }

    function saveProduct() {
        const name = document.getElementById('productName').value;
        const quantity = parseInt(document.getElementById('productQuantity').value);
        const price = parseFloat(document.getElementById('productPrice').value);
        if (name && quantity > 0 && price >= 0) {
            products.push({ name, quantity, price });
            updateProductList();
            closeModal();
        } else {
            alert('Completa todos los campos.');
        }
    }

    function removeProduct(index) {
        products.splice(index, 1);
        updateProductList();
    }

    updateProductList();

