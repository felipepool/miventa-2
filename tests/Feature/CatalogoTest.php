<?php

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminCatalogo(): User
{
    return User::factory()->for(Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
}

test('catalog endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/marcas'], ['postJson', '/api/v1/marcas'], ['putJson', '/api/v1/marcas/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['patchJson', '/api/v1/marcas/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
    ['getJson', '/api/v1/categorias'], ['postJson', '/api/v1/categorias'], ['patchJson', '/api/v1/categorias/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
    ['getJson', '/api/v1/productos'], ['getJson', '/api/v1/productos/opciones'], ['postJson', '/api/v1/productos'], ['putJson', '/api/v1/productos/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['patchJson', '/api/v1/productos/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
]);

test('users without catalog permission receive 403', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $marca = Marca::factory()->for($usuario->negocio)->create(['nombre' => 'Original']);
    $producto = Producto::factory()->for($usuario->negocio)->create();
    $this->actingAs($usuario)->getJson('/api/v1/marcas')->assertForbidden();
    $this->getJson('/api/v1/categorias')->assertForbidden();
    $this->getJson('/api/v1/productos')->assertForbidden();
    $this->getJson('/api/v1/productos/opciones')->assertForbidden();
    $this->postJson('/api/v1/marcas', ['nombre' => 'No creada'])->assertForbidden();
    $this->putJson('/api/v1/marcas/'.$marca->public_id, ['nombre' => 'No editada'])->assertForbidden();
    $this->patchJson('/api/v1/productos/'.$producto->public_id.'/estado', ['activo' => false])->assertForbidden();
    expect($marca->fresh()->nombre)->toBe('Original');
    expect($producto->fresh()->activo)->toBeTrue();
    expect(Marca::count())->toBe(1);
})->with(['cajero', 'cocina', 'garzon']);

test('catalog routes require a confirmed branch when several are available', function () {
    $admin = adminCatalogo();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->getJson('/api/v1/productos')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
    $this->postJson('/api/v1/marcas', ['nombre' => 'No creada'])->assertStatus(409);
    $this->assertDatabaseMissing('marcas', ['nombre' => 'No creada']);
});

test('brands and categories are created, listed, filtered and renamed within the business', function (string $recurso, string $modelo) {
    $admin = adminCatalogo();
    $ajena = $modelo::factory()->create(['nombre' => 'Ajena']);
    $this->actingAs($admin);
    $id = $this->postJson("/api/v1/{$recurso}", ['nombre' => '  Alfa  ', 'activa' => false, 'negocio_id' => $ajena->negocio_id])
        ->assertCreated()->assertJsonPath('data.nombre', 'Alfa')->assertJsonPath('data.activa', true)->json('data.id');
    expect($modelo::sole()->negocio_id)->toBe($admin->negocio_id);
    $inactiva = $modelo::factory()->for($admin->negocio)->inactiva()->create(['nombre' => 'Beta']);
    $modelo::factory()->for($admin->negocio)->count(15)->create();
    $this->getJson("/api/v1/{$recurso}")->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17)
        ->assertJsonMissing(['id' => $ajena->public_id]);
    $this->getJson("/api/v1/{$recurso}?estado=inactiva&buscar=bet")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactiva->public_id);
    $this->getJson("/api/v1/{$recurso}?buscar=".urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    $this->putJson("/api/v1/{$recurso}/{$id}", ['nombre' => 'Alfa 2'])->assertOk()->assertJsonPath('data.nombre', 'Alfa 2');
    $this->putJson("/api/v1/{$recurso}/{$id}", ['nombre' => 'alfa 2'])->assertOk();
    $this->getJson("/api/v1/{$recurso}/{$id}")->assertOk()->assertJsonPath('data.nombre', 'alfa 2');
})->with([['marcas', Marca::class], ['categorias', Categoria::class]]);

test('invalid or duplicated names are rejected for brands and categories', function (string $recurso, string $modelo, array $datos) {
    $admin = adminCatalogo();
    $modelo::factory()->for($admin->negocio)->create(['nombre' => 'Existente']);
    $otra = $modelo::factory()->for($admin->negocio)->create(['nombre' => 'Otra']);
    $this->actingAs($admin)->postJson("/api/v1/{$recurso}", $datos)->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->putJson("/api/v1/{$recurso}/{$otra->public_id}", $datos)->assertUnprocessable()->assertJsonValidationErrors('nombre');
    expect($modelo::count())->toBe(2);
})->with([
    ['marcas', Marca::class, ['nombre' => '   ']], ['marcas', Marca::class, ['nombre' => str_repeat('x', 101)]],
    ['marcas', Marca::class, ['nombre' => 'existente']],
    ['categorias', Categoria::class, ['nombre' => '   ']], ['categorias', Categoria::class, ['nombre' => str_repeat('x', 101)]],
    ['categorias', Categoria::class, ['nombre' => 'EXISTENTE']],
]);

test('the same name can exist in two businesses', function () {
    $admin = adminCatalogo();
    Marca::factory()->create(['nombre' => 'Compartida']);
    $this->actingAs($admin)->postJson('/api/v1/marcas', ['nombre' => 'Compartida'])->assertCreated();
});

test('brands and categories reject non-text names instead of converting them', function (string $recurso, string $modelo) {
    $admin = adminCatalogo();
    $registro = $modelo::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->postJson("/api/v1/{$recurso}", ['nombre' => ['No válido']])
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->putJson("/api/v1/{$recurso}/{$registro->public_id}", ['nombre' => ['No válido']])
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    expect($modelo::where('nombre', 'Array')->exists())->toBeFalse();
})->with([['marcas', Marca::class], ['categorias', Categoria::class]]);

test('foreign catalog records return 404', function () {
    $admin = adminCatalogo();
    $marca = Marca::factory()->create();
    $categoria = Categoria::factory()->create();
    $producto = Producto::factory()->create();
    $this->actingAs($admin);
    $this->getJson('/api/v1/marcas/'.$marca->public_id)->assertNotFound();
    $this->putJson('/api/v1/marcas/'.$marca->public_id, ['nombre' => 'X'])->assertNotFound();
    $this->patchJson('/api/v1/categorias/'.$categoria->public_id.'/estado', ['activa' => false])->assertNotFound();
    $this->getJson('/api/v1/productos/'.$producto->public_id)->assertNotFound();
    $this->putJson('/api/v1/productos/'.$producto->public_id, productoValido($admin))->assertNotFound();
    $this->patchJson('/api/v1/productos/'.$producto->public_id.'/estado', ['activo' => false])->assertNotFound();
    expect($marca->fresh()->nombre)->not->toBe('X');
    expect($categoria->fresh()->activa)->toBeTrue();
    expect($producto->fresh()->activo)->toBeTrue();
});

test('brands, categories and products are deactivated and reactivated without being deleted', function () {
    $admin = adminCatalogo();
    $marca = Marca::factory()->for($admin->negocio)->create();
    $categoria = Categoria::factory()->for($admin->negocio)->create();
    $producto = Producto::factory()->for($admin->negocio)->create(['categoria_id' => $categoria->id, 'marca_id' => $marca->id]);
    $this->actingAs($admin);
    $this->patchJson('/api/v1/marcas/'.$marca->public_id.'/estado', ['activa' => false])->assertOk()->assertJsonPath('data.activa', false)->assertJsonPath('data.productos_count', 1);
    $this->patchJson('/api/v1/categorias/'.$categoria->public_id.'/estado', ['activa' => false])->assertOk()->assertJsonPath('data.activa', false);
    $this->patchJson('/api/v1/productos/'.$producto->public_id.'/estado', ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
    expect($producto->fresh()->only(['categoria_id', 'marca_id']))->toBe(['categoria_id' => $categoria->id, 'marca_id' => $marca->id]);
    $this->patchJson('/api/v1/productos/'.$producto->public_id.'/estado', ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);
    $this->patchJson('/api/v1/productos/'.$producto->public_id.'/estado', ['activo' => 'quizá'])->assertUnprocessable()->assertJsonValidationErrors('activo');
    $this->deleteJson('/api/v1/productos/'.$producto->public_id)->assertStatus(405);
});

function productoValido(User $admin, array $cambios = []): array
{
    $categoria = Categoria::withoutGlobalScopes()->where('negocio_id', $admin->negocio_id)->where('nombre', 'Bebidas')->first()
        ?? Categoria::factory()->for($admin->negocio)->create(['nombre' => 'Bebidas']);

    return ['nombre' => 'Bebida 500 ml', 'categoria' => $categoria->public_id, 'unidad_venta' => 'unidad',
        'precio' => 1500, 'controla_stock' => true, ...$cambios];
}

test('a product is created with its category, optional brand, price and normalized fields', function () {
    $admin = adminCatalogo();
    $marca = Marca::factory()->for($admin->negocio)->create();
    $ajena = Negocio::factory()->create();
    $this->actingAs($admin);
    $id = $this->postJson('/api/v1/productos', productoValido($admin, [
        'nombre' => '  Bebida 500 ml  ', 'marca' => $marca->public_id, 'sku' => ' BEB-1 ', 'codigo_barras' => '780123', 'descripcion' => '',
        'costo' => '900', 'unidad_venta' => 'kg', 'negocio_id' => $ajena->id, 'activo' => false,
    ]))->assertCreated()->assertJsonPath('data.nombre', 'Bebida 500 ml')->assertJsonPath('data.sku', 'BEB-1')
        ->assertJsonPath('data.descripcion', null)->assertJsonPath('data.precio', 1500)->assertJsonPath('data.costo', 900)
        ->assertJsonPath('data.unidad_venta', 'kg')->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.marca.id', $marca->public_id)->assertJsonPath('data.categoria.nombre', 'Bebidas')->json('data.id');
    expect(Producto::sole()->negocio_id)->toBe($admin->negocio_id);
    $this->postJson('/api/v1/productos', productoValido($admin, ['nombre' => 'Sin marca']))->assertCreated()->assertJsonPath('data.marca', null);
    $this->getJson('/api/v1/productos/'.$id)->assertOk()->assertJsonPath('data.codigo_barras', '780123');
});

test('invalid product data returns 422', function (array $cambios, string $campo) {
    $admin = adminCatalogo();
    $this->actingAs($admin)->postJson('/api/v1/productos', productoValido($admin, $cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Producto::count())->toBe(0);
})->with([
    'empty name' => [['nombre' => ' '], 'nombre'], 'long name' => [['nombre' => str_repeat('x', 151)], 'nombre'],
    'unknown category' => [['categoria' => '01m5n22fn4rjjt0zc1mwgd9faa'], 'categoria'],
    'bad unit' => [['unidad_venta' => 'l'], 'unidad_venta'], 'missing price' => [['precio' => null], 'precio'],
    'decimal price' => [['precio' => 10.5], 'precio'], 'negative price' => [['precio' => -1], 'precio'],
    'huge price' => [['precio' => 100_000_000], 'precio'], 'negative cost' => [['costo' => -5], 'costo'],
    'long sku' => [['sku' => str_repeat('x', 31)], 'sku'], 'unknown brand' => [['marca' => '01m5n22fn4rjjt0zc1mwgd9faa'], 'marca'],
    'missing stock flag' => [['controla_stock' => null], 'controla_stock'],
    'array name' => [['nombre' => ['No válido']], 'nombre'],
    'array sku' => [['sku' => ['No válido']], 'sku'],
    'array description' => [['descripcion' => ['No válido']], 'descripcion'],
]);

test('a product can be created, listed and edited without a category', function (?string $categoriaVacia) {
    $admin = adminCatalogo();
    $this->actingAs($admin);
    $id = $this->postJson('/api/v1/productos', productoValido($admin, ['nombre' => 'Sin categoría', 'categoria' => $categoriaVacia]))
        ->assertCreated()->assertJsonPath('data.categoria', null)->json('data.id');
    expect(Producto::sole()->categoria_id)->toBeNull();
    $this->getJson('/api/v1/productos')->assertOk()->assertJsonPath('data.0.categoria', null);

    $this->putJson('/api/v1/productos/'.$id, productoValido($admin, ['nombre' => 'Con categoría']))->assertOk()->assertJsonPath('data.categoria.nombre', 'Bebidas');
    $this->putJson('/api/v1/productos/'.$id, productoValido($admin, ['categoria' => '']))->assertOk()->assertJsonPath('data.categoria', null);
    expect(Producto::sole()->categoria_id)->toBeNull();
})->with([[null], ['']]);

test('a product with an explicitly entered zero price is valid', function () {
    $admin = adminCatalogo();
    $this->actingAs($admin)->postJson('/api/v1/productos', productoValido($admin, ['precio' => 0]))
        ->assertCreated()->assertJsonPath('data.precio', 0);
    expect(Producto::sole()->precio)->toBe(0);
});

test('sku and barcode are unique per business but reusable across businesses', function () {
    $admin = adminCatalogo();
    Producto::factory()->for($admin->negocio)->create(['sku' => 'ABC', 'codigo_barras' => '111']);
    Producto::factory()->create(['sku' => 'XYZ', 'codigo_barras' => '222']);
    $propio = Producto::factory()->for($admin->negocio)->create(['sku' => 'DEF']);
    $this->actingAs($admin);
    $this->postJson('/api/v1/productos', productoValido($admin, ['sku' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('sku');
    $this->postJson('/api/v1/productos', productoValido($admin, ['codigo_barras' => '111']))->assertUnprocessable()->assertJsonValidationErrors('codigo_barras');
    $this->putJson('/api/v1/productos/'.$propio->public_id, productoValido($admin, ['sku' => 'ABC']))->assertUnprocessable()->assertJsonValidationErrors('sku');
    $this->putJson('/api/v1/productos/'.$propio->public_id, productoValido($admin, ['sku' => 'DEF']))->assertOk();
    $this->postJson('/api/v1/productos', productoValido($admin, ['sku' => 'XYZ', 'codigo_barras' => '222']))->assertCreated();
    $this->postJson('/api/v1/productos', productoValido($admin, ['nombre' => 'Otro sin códigos']))->assertCreated();
    $this->postJson('/api/v1/productos', productoValido($admin, ['nombre' => 'Otro más sin códigos']))->assertCreated();
});

test('foreign or inactive categories and brands cannot be assigned but an existing one is kept', function () {
    $admin = adminCatalogo();
    $ajenaCategoria = Categoria::factory()->create();
    $ajenaMarca = Marca::factory()->create();
    $inactivaCategoria = Categoria::factory()->for($admin->negocio)->inactiva()->create();
    $inactivaMarca = Marca::factory()->for($admin->negocio)->inactiva()->create();
    $this->actingAs($admin);
    $this->postJson('/api/v1/productos', productoValido($admin, ['categoria' => $ajenaCategoria->public_id]))->assertUnprocessable()->assertJsonValidationErrors('categoria');
    $this->postJson('/api/v1/productos', productoValido($admin, ['marca' => $ajenaMarca->public_id]))->assertUnprocessable()->assertJsonValidationErrors('marca');
    $this->postJson('/api/v1/productos', productoValido($admin, ['categoria' => $inactivaCategoria->public_id]))->assertUnprocessable()->assertJsonValidationErrors('categoria');
    $this->postJson('/api/v1/productos', productoValido($admin, ['marca' => $inactivaMarca->public_id]))->assertUnprocessable()->assertJsonValidationErrors('marca');
    expect(Producto::count())->toBe(0);

    $producto = Producto::factory()->for($admin->negocio)->create(['categoria_id' => $inactivaCategoria->id, 'marca_id' => $inactivaMarca->id]);
    $datos = productoValido($admin, ['nombre' => 'Renombrado', 'categoria' => $inactivaCategoria->public_id, 'marca' => $inactivaMarca->public_id]);
    $this->putJson('/api/v1/productos/'.$producto->public_id, $datos)->assertOk()->assertJsonPath('data.categoria.activa', false)->assertJsonPath('data.marca.activa', false);
    $this->putJson('/api/v1/productos/'.$producto->public_id, [...$datos, 'marca' => null])->assertOk()->assertJsonPath('data.marca', null);
    $this->putJson('/api/v1/productos/'.$producto->public_id, [...$datos, 'marca' => $inactivaMarca->public_id])->assertUnprocessable()->assertJsonValidationErrors('marca');
});

test('products are paginated searchable and filterable without leaking another business', function () {
    $admin = adminCatalogo();
    $bebidas = Categoria::factory()->for($admin->negocio)->create(['nombre' => 'Bebidas']);
    $marca = Marca::factory()->for($admin->negocio)->create();
    Producto::factory()->for($admin->negocio)->count(15)->create();
    $cola = Producto::factory()->for($admin->negocio)->create(['nombre' => 'Cola', 'sku' => 'COL-1', 'codigo_barras' => '999', 'categoria_id' => $bebidas->id, 'marca_id' => $marca->id]);
    $inactivo = Producto::factory()->for($admin->negocio)->inactivo()->create(['nombre' => 'Agua']);
    $ajeno = Producto::factory()->create(['nombre' => 'Cola ajena']);
    $categoriaAjena = Categoria::withoutGlobalScopes()->findOrFail($ajeno->categoria_id);
    $this->actingAs($admin);
    $this->getJson('/api/v1/productos')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17)
        ->assertJsonMissing(['id' => $ajeno->public_id])->assertJsonPath('data.0.nombre', 'Agua');
    $this->getJson('/api/v1/productos?buscar=col')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cola->public_id);
    $this->getJson('/api/v1/productos?buscar=999')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/productos?buscar=COL-1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/productos?categoria='.$bebidas->public_id.'&marca='.$marca->public_id)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/productos?estado=inactivo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactivo->public_id);
    $this->getJson('/api/v1/productos?buscar='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/productos?estado=otro')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/v1/productos?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('categorias')->assertJsonMissingPath('marcas');
    $this->getJson('/api/v1/productos/opciones')->assertOk()->assertJsonCount(17, 'categorias')->assertJsonCount(1, 'marcas')
        ->assertJsonMissing(['id' => $categoriaAjena->public_id]);
});

test('a read-only business cannot modify the catalog but can read it', function () {
    $admin = adminCatalogo();
    $producto = Producto::factory()->for($admin->negocio)->create(['nombre' => 'Intacto']);
    $this->actingAs($admin)->getJson('/api/v1/productos')->assertOk();
    $this->getJson('/api/v1/productos/opciones')->assertOk();
    $admin->negocio->suscripciones()->delete();
    $this->getJson('/api/v1/productos')->assertOk();
    $this->getJson('/api/v1/productos/opciones')->assertOk();
    $this->postJson('/api/v1/marcas', ['nombre' => 'No creada'])->assertForbidden()->assertJsonPath('code', 'negocio_solo_lectura');
    $this->putJson('/api/v1/productos/'.$producto->public_id, productoValido($admin, ['nombre' => 'Cambiado']))->assertForbidden();
    expect($producto->fresh()->nombre)->toBe('Intacto');
});
