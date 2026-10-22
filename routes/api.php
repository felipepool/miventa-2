<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

// API JSON versionada. Hoy la consume la SPA con la cookie de sesión (Sanctum en
// modo SPA); más adelante también terceros con tokens de Sanctum.
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', MeController::class)->name('me');
});
