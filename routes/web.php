<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Solo login/logout: no hay registro público.
Auth::routes([
    'register' => false,
    'reset' => false,
    'confirm' => false,
    'verify' => false,
]);

Route::redirect('/', '/app');

// Cascarón de la SPA: react-router resuelve las rutas bajo /app.
Route::view('app/{any?}', 'app')
    ->where('any', '.*')
    ->middleware('auth')
    ->name('app');
