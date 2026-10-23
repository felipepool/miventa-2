<?php

use App\Http\Controllers\Api\V1\CambiarEstadoSucursalController;
use App\Http\Controllers\Api\V1\CambiarEstadoUsuarioController;
use App\Http\Controllers\Api\V1\CotizacionSucursalController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\SucursalActualController;
use App\Http\Controllers\Api\V1\SucursalController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

// API JSON versionada. Hoy la consume la SPA con la cookie de sesión (Sanctum en
// modo SPA); más adelante también terceros con tokens de Sanctum.
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', MeController::class)->name('me');
    Route::put('sucursal-actual/{sucursal}', SucursalActualController::class)->name('sucursal-actual');

    Route::middleware('can:gestionar_usuarios')->group(function () {
        Route::get('roles', RolController::class)->name('roles.index');
        Route::apiResource('usuarios', UsuarioController::class)->only(['index', 'show', 'store', 'update']);
        Route::patch('usuarios/{usuario}/estado', CambiarEstadoUsuarioController::class)->name('usuarios.estado');
    });

    Route::middleware('can:gestionar_sucursales')->group(function () {
        Route::apiResource('sucursales', SucursalController::class)
            ->only(['index', 'show', 'store', 'update'])
            ->parameters(['sucursales' => 'sucursal']);
        Route::patch('sucursales/{sucursal}/estado', CambiarEstadoSucursalController::class)->name('sucursales.estado');
        Route::get('sucursales/{sucursal}/cotizacion', CotizacionSucursalController::class)->name('sucursales.cotizacion');
    });
});
