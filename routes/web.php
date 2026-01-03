<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

// Fallback para servir recursos si el enlace simbólico falla en modo desarrollo
Route::get('/storage/recursos/{filename}', function ($filename) {
    $path = storage_path('app/public/recursos/' . $filename);
    if (!File::exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/storage/guias/{filename}', function ($filename) {
    $path = storage_path('app/public/guias/' . $filename);
    if (!File::exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/storage/generos/{filename}', function ($filename) {
    $path = storage_path('app/public/generos/' . $filename);
    if (!File::exists($path)) {
        abort(404);
    }
    return response()->file($path);
});
