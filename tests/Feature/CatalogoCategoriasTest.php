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

it('permite al rol sistema desactivar y enviar productos a papelera de forma masiva', function () {
    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $productoA = Producto::query()->create([
        'nombre' => 'Producto masivo A',
        'numero_parte' => 'MAS-A',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para accion masiva',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $productoB = Producto::query()->create([
        'nombre' => 'Producto masivo B',
        'numero_parte' => 'MAS-B',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para accion masiva',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $this
        ->actingAs($sistema)
        ->post(route('catalogo.productos.bulk'), [
            'action' => 'desactivar',
            'productos' => [$productoA->codigo_producto],
        ])
        ->assertSessionHas('success');

    expect($productoA->fresh()->activo)->toBeFalse();

    $this
        ->actingAs($sistema)
        ->post(route('catalogo.productos.bulk'), [
            'action' => 'eliminar',
            'productos' => [$productoA->codigo_producto, $productoB->codigo_producto],
        ])
        ->assertSessionHas('success');

    expect(Producto::onlyTrashed()->whereKey($productoA->codigo_producto)->exists())->toBeTrue()
        ->and(Producto::onlyTrashed()->whereKey($productoB->codigo_producto)->exists())->toBeTrue();
});

it('muestra controles de vista seleccion multiple y paginacion para rol sistema', function () {
    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    Producto::query()->create([
        'nombre' => 'Producto control vista',
        'numero_parte' => 'VIEW-CTRL-001',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para controles de vista',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $response = $this
        ->actingAs($sistema)
        ->get(route('catalogo.index', ['per_page' => 24]))
        ->assertOk()
        ->assertSee('Tarjetas compactas')
        ->assertSee('Lista')
        ->assertSee('Seleccion multiple')
        ->assertSee('Seleccionar p')
        ->assertSee('Mostrar');

    expect($response->viewData('productos')->perPage())->toBe(24);
});

it('solo permite al rol sistema cambiar cuantos productos aparecen por pagina', function () {
    $gerente = User::factory()->create([
        'puesto' => 'gerente',
    ]);

    Producto::query()->create([
        'nombre' => 'Producto paginacion gerente',
        'numero_parte' => 'PAGE-GER-001',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto para paginacion',
        'activo' => true,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $response = $this
        ->actingAs($gerente)
        ->get(route('catalogo.index', ['per_page' => 96]))
        ->assertOk()
        ->assertDontSee('name="per_page"', false);

    expect($response->viewData('productos')->perPage())->toBe(12);
});

it('permite recuperar productos de papelera antes de 20 dias y purga vencidos', function () {
    $sistema = User::factory()->create([
        'puesto' => 'sistema',
    ]);

    $recuperable = Producto::query()->create([
        'nombre' => 'Producto recuperable',
        'numero_parte' => 'REC-001',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto en papelera',
        'activo' => false,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $vencido = Producto::query()->create([
        'nombre' => 'Producto vencido',
        'numero_parte' => 'VEN-001',
        'categoria' => 'General',
        'clave_prodserv' => '43222600',
        'unidad' => 'PZA',
        'stock_seguridad' => 0,
        'descripcion' => 'Producto vencido',
        'activo' => false,
        'stock_total' => 0,
        'stock_paquetes' => 0,
        'stock_piezas_sueltas' => 0,
    ]);

    $recuperable->delete();
    $vencido->delete();
    $vencido->forceFill(['deleted_at' => now()->subDays(21)])->save();

    $this
        ->actingAs($sistema)
        ->put(route('producto.restaurar', $recuperable->codigo_producto))
        ->assertSessionHas('success');

    $this
        ->actingAs($sistema)
        ->get(route('catalogo.index', ['papelera' => 1]))
        ->assertOk();

    expect(Producto::query()->whereKey($recuperable->codigo_producto)->exists())->toBeTrue()
        ->and(Producto::withTrashed()->whereKey($vencido->codigo_producto)->exists())->toBeFalse();
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
