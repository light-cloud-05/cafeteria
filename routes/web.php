<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\RolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::prefix("rol")->controller(RolController::class)->group(function(){
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::post('/update', 'update');
    Route::post('/{Rol}', 'delete');
    Route::get('/{id}', 'show');
});