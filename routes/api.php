<?php

use App\Http\Controllers\Api\V1\CajaController;
use App\Http\Controllers\Api\V1\CambiarEstadoCajaController;
use App\Http\Controllers\Api\V1\CambiarEstadoCategoriaController;
use App\Http\Controllers\Api\V1\CambiarEstadoGrupoModificadorController;
use App\Http\Controllers\Api\V1\CambiarEstadoMarcaController;
use App\Http\Controllers\Api\V1\CambiarEstadoProductoController;
use App\Http\Controllers\Api\V1\CambiarEstadoSucursalController;
use App\Http\Controllers\Api\V1\CambiarEstadoUsuarioController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\CotizacionCajaController;
use App\Http\Controllers\Api\V1\CotizacionSucursalController;
use App\Http\Controllers\Api\V1\GrupoModificadorController;
use App\Http\Controllers\Api\V1\InventarioController;
use App\Http\Controllers\Api\V1\MarcaController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MiCajaController;
use App\Http\Controllers\Api\V1\MiPlanController;
use App\Http\Controllers\Api\V1\ModulosNegocioController;
use App\Http\Controllers\Api\V1\NegocioController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PreferenciasNegocioController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\SucursalActualController;
use App\Http\Controllers\Api\V1\SucursalController;
use App\Http\Controllers\Api\V1\TurnoCajaController;
use App\Http\Controllers\Api\V1\UsuarioController;
use App\Http\Controllers\Api\V1\VentaController;
use App\Http\Middleware\RequerirNegocioOperativo;
use App\Http\Middleware\RequerirSucursalActual;
use Illuminate\Support\Facades\Route;

// API JSON versionada. Hoy la consume la SPA con la cookie de sesión (Sanctum en
// modo SPA); más adelante también terceros con tokens de Sanctum.
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api', RequerirNegocioOperativo::class])->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::put('sucursal-actual/{sucursal}', SucursalActualController::class)->name('sucursal-actual');

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_negocio'])->group(function () {
        Route::get('negocio', [NegocioController::class, 'show'])->name('negocio.show');
        Route::put('negocio', [NegocioController::class, 'update'])->name('negocio.update');
        Route::put('negocio/modulos', ModulosNegocioController::class)->name('negocio.modulos');
        Route::put('negocio/preferencias', PreferenciasNegocioController::class)->name('negocio.preferencias');
        Route::get('plan', [MiPlanController::class, 'show'])->name('plan.show');
        Route::get('plan/cotizacion', [MiPlanController::class, 'cotizacion'])->name('plan.cotizacion');
        Route::put('plan', [MiPlanController::class, 'update'])->name('plan.update');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:vender'])->group(function () {
        Route::get('pos', PosController::class)->name('pos');
        Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:operar_caja'])->group(function () {
        Route::get('caja', [MiCajaController::class, 'show'])->name('caja.show');
        Route::post('caja/abrir', [MiCajaController::class, 'abrir'])->name('caja.abrir');
        Route::post('caja/movimientos', [MiCajaController::class, 'registrarMovimiento'])->name('caja.movimientos');
        Route::post('caja/cerrar', [MiCajaController::class, 'cerrar'])->name('caja.cerrar');
        Route::get('turnos', [TurnoCajaController::class, 'index'])->name('turnos.index');
        Route::get('turnos/{turnoCaja}', [TurnoCajaController::class, 'show'])->name('turnos.show');
        Route::post('turnos/{turnoCaja}/cerrar', [TurnoCajaController::class, 'cerrar'])->middleware('can:gestionar_cajas')->name('turnos.cerrar');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_cajas'])->group(function () {
        Route::get('cajas/cotizacion', CotizacionCajaController::class)->name('cajas.cotizacion');
        Route::apiResource('cajas', CajaController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('cajas/{caja}/estado', CambiarEstadoCajaController::class)->name('cajas.estado');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_usuarios'])->group(function () {
        Route::get('roles', RolController::class)->name('roles.index');
        Route::apiResource('usuarios', UsuarioController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('usuarios/{usuario}/estado', CambiarEstadoUsuarioController::class)->name('usuarios.estado');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_catalogo'])->group(function () {
        Route::apiResource('marcas', MarcaController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('marcas/{marca}/estado', CambiarEstadoMarcaController::class)->name('marcas.estado');
        Route::apiResource('categorias', CategoriaController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('categorias/{categoria}/estado', CambiarEstadoCategoriaController::class)->name('categorias.estado');
        Route::get('productos/opciones', [ProductoController::class, 'opciones'])->name('productos.opciones');
        Route::apiResource('productos', ProductoController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('productos/{producto}/estado', CambiarEstadoProductoController::class)->name('productos.estado');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_catalogo', 'modulo:modificadores'])->group(function () {
        Route::get('grupos-modificadores', [GrupoModificadorController::class, 'index'])->name('grupos-modificadores.index');
        Route::post('grupos-modificadores', [GrupoModificadorController::class, 'store'])->name('grupos-modificadores.store');
        Route::get('grupos-modificadores/{grupoModificador}', [GrupoModificadorController::class, 'show'])->name('grupos-modificadores.show');
        Route::put('grupos-modificadores/{grupoModificador}', [GrupoModificadorController::class, 'update'])->name('grupos-modificadores.update');
        Route::patch('grupos-modificadores/{grupoModificador}/estado', CambiarEstadoGrupoModificadorController::class)->name('grupos-modificadores.estado');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_inventario', 'adicional:inventario'])->group(function () {
        Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
        Route::get('inventario/{producto}/movimientos', [InventarioController::class, 'movimientos'])->name('inventario.movimientos');
        Route::post('inventario/{producto}/movimientos', [InventarioController::class, 'registrarMovimiento'])->name('inventario.movimientos.store');
        Route::put('inventario/{producto}/minimo', [InventarioController::class, 'fijarMinimo'])->name('inventario.minimo');
    });

    Route::middleware([RequerirSucursalActual::class, 'can:gestionar_sucursales'])->group(function () {
        Route::apiResource('sucursales', SucursalController::class)
            ->only(['index', 'show', 'store', 'update'])
            ->parameters(['sucursales' => 'sucursal']);
        Route::patch('sucursales/{sucursal}/estado', CambiarEstadoSucursalController::class)->name('sucursales.estado');
        Route::get('sucursales/{sucursal}/cotizacion', CotizacionSucursalController::class)->name('sucursales.cotizacion');
    });
});
