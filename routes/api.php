<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PetitionController;
use App\Http\Controllers\CategoryController;

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

Route::get('peticiones', [PetitionController::class, 'index']);
Route::get('peticiones/{id}', [PetitionController::class, 'show']);

Route::get('categorias', [CategoryController::class, 'index']); 
Route::middleware('api')->post('refresh', [AuthController::class, 'refresh']);


Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']); 
    Route::post('peticiones', [PetitionController::class, 'store']);
    Route::put('peticiones/{id}', [PetitionController::class, 'update']);
    Route::delete('peticiones/{id}', [PetitionController::class, 'destroy']);
    Route::post('peticiones/firmar/{id}', [PetitionController::class, 'firmar']);
});