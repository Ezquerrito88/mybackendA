<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PetitionController;

// Públicas
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::get('peticiones', [PetitionController::class, 'index']);
Route::get('peticiones/{id}', [PetitionController::class, 'show']);

// Refresh (IMPORTANTE: Fuera de auth:api)
Route::middleware('api')->post('refresh', [AuthController::class, 'refresh']);

// Privadas
Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    
    Route::post('peticiones', [PetitionController::class, 'store']);
    Route::put('peticiones/{id}', [PetitionController::class, 'update']);
    Route::delete('peticiones/{id}', [PetitionController::class, 'destroy']);
    Route::put('peticiones/firmar/{id}', [PetitionController::class, 'firmar']);
});
