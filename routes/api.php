<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

// API JSON de la SPA. Sanctum en modo SPA: autentica con la cookie de sesión del login Blade.
Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('me', MeController::class)->name('me');
});
