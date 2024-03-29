<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return response()->json(['redireccionado al inicio']);
});

Route::get('/subidos/{filename}', function ($filename) {
    $path = Storage::disk('subidos')->path($filename);
    return response()->file($path);
});

Route::get('/Remesa01/{filename}', function ($filename) {
    $path = Storage::disk('expedientes')->path($filename);
    return response()->file($path);
});
