<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// La autenticación (POST /login, /logout, /forgot-password, /reset-password)
// la registra Fortify sin vistas: responde JSON a la SPA.

Route::redirect('/', '/dashboard');

// Destino del enlace del correo de recuperación (sin vistas, Fortify no define esta ruta).
Route::view('restablecer-contrasena/{token}', 'app')->name('password.reset');

// Cascarón de la SPA: react-router resuelve cualquier otra ruta GET y decide
// qué es público y qué requiere sesión. La API nunca devuelve el cascarón.
Route::fallback(function (Request $request) {
    abort_if($request->is('api/*'), 404);

    return view('app');
})->name('spa');
