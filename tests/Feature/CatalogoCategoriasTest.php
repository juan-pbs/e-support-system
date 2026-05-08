<?php

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\User;

it('permite guardar productos con numero de parte alfanumerico y simbolos', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('producto.guardar'), [
            'nombre' => 'Camara bullet',
            'numero_parte' => 'ABC/123-XY+#',
            'categoria' => 'Videovigilancia',
            'clave_prodserv' => '26121609',
            'unidad' => 'PZA',
            'stock_seguridad' => 3,
            'descripcion' => 'Camara para exterior',
        ]);

    $response->assertRedirect(route('catalogo.index'));

    expect(Producto::query()->where('numero_parte', 'ABC/123-XY+#')->exists())->toBeTrue()
        ->and(CategoriaProducto::query()->where('nombre', 'Videovigilancia')->exists())->toBeTrue();
});

it('valida que la categoria sea obligatoria al guardar productos', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->from(route('producto.crear'))
        ->post(route('producto.guardar'), [
            'nombre' => 'Producto sin categoria',
            'numero_parte' => 'PC-0001',
            'categoria' => '',
            'categoria_nueva' => '',
            'clave_prodserv' => '26121609',
            'unidad' => 'PZA',
            'stock_seguridad' => 0,
            'descripcion' => 'Prueba de validacion',
        ]);

    $response
        ->assertRedirect(route('producto.crear'))
        ->assertSessionHasErrors(['categoria']);
});

it('permite guardar un producto usando una categoria nueva escrita en el formulario', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->post(route('producto.guardar'), [
            'nombre' => 'Panel de alarma',
            'numero_parte' => 'ALM-01',
            'categoria' => '',
            'categoria_nueva' => 'Alarmas especiales',
            'clave_prodserv' => '46171619',
            'unidad' => 'PZA',
            'stock_seguridad' => 1,
            'descripcion' => 'Panel de control',
        ]);

    $response->assertRedirect(route('catalogo.index'));

    expect(Producto::query()->where('numero_parte', 'ALM-01')->where('categoria', 'Alarmas especiales')->exists())->toBeTrue()
        ->and(CategoriaProducto::query()->where('nombre', 'Alarmas especiales')->exists())->toBeTrue();
});

it('renombra categorias y actualiza los productos relacionados', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $categoria = CategoriaProducto::query()->create([
        'nombre' => 'ControlAcceso',
    ]);

    $producto = Producto::query()->create([
        'nombre' => 'Lector biometrico',
        'numero_parte' => 'BIO-100',
        'categoria' => 'ControlAcceso',
        'clave_prodserv' => '43211708',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Equipo de acceso',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->put(route('catalogo.categorias.actualizar', $categoria->id), [
            'nombre' => 'Control de Acceso',
        ]);

    $response->assertSessionHas('success');

    expect($categoria->fresh()->nombre)->toBe('Control de Acceso')
        ->and($producto->fresh()->categoria)->toBe('Control de Acceso');
});

it('impide eliminar una categoria que ya tiene productos', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    $categoria = CategoriaProducto::query()->create([
        'nombre' => 'CCTV-ZonaNorte',
    ]);

    Producto::query()->create([
        'nombre' => 'DVR 16 canales',
        'numero_parte' => 'DVR-016',
        'categoria' => 'CCTV-ZonaNorte',
        'clave_prodserv' => '43222619',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Grabador',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->delete(route('catalogo.categorias.eliminar', $categoria->id));

    $response->assertSessionHas('error');

    expect(CategoriaProducto::query()->whereKey($categoria->id)->exists())->toBeTrue();
});

it('muestra la administracion de categorias junto al formulario de producto', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    CategoriaProducto::query()->create([
        'nombre' => 'Energia',
    ]);

    $response = $this
        ->actingAs($gerente)
        ->get(route('producto.crear'));

    $response->assertOk()
        ->assertSee('Administrar categorías')
        ->assertSee('Energia');
});

it('muestra el selector desglosado de categorias en crear y editar producto', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    CategoriaProducto::query()->create([
        'nombre' => 'Seguridad perimetral',
    ]);

    $producto = Producto::query()->create([
        'nombre' => 'Sensor magnetico',
        'numero_parte' => 'SM-100',
        'categoria' => 'Seguridad perimetral',
        'clave_prodserv' => '46171611',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Sensor para puertas',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $crear = $this
        ->actingAs($gerente)
        ->get(route('producto.crear'));

    $crear->assertOk()
        ->assertSee('Categorías base')
        ->assertSee('Seguridad perimetral')
        ->assertSee('Si no está en la lista, escribe una nueva categoría')
        ->assertSee('Administrar categorías');

    $editar = $this
        ->actingAs($gerente)
        ->get(route('producto.editar', $producto->codigo_producto));

    $editar->assertOk()
        ->assertSee('Categorías base')
        ->assertSee('Seguridad perimetral')
        ->assertSee('Si necesitas una nueva categoría, escríbela aquí')
        ->assertSee('Administrar categorías');
});
