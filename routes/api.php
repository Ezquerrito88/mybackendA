<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PetitionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Admin\PetitionController as AdminPetitionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;

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
    Route::get('mispeticiones', [PetitionController::class, 'listMine']);
    Route::post('peticiones/firmar/{id}', [PetitionController::class, 'firmar']);
});

// ─── PANEL DE ADMINISTRACIÓN ─────────────────────────────────────────────────
Route::prefix('admin')->middleware(['auth:api', 'isAdmin'])->group(function () {

    // Peticiones CRUD + cambio de estado
    Route::get('peticiones',                  [AdminPetitionController::class, 'index']);
    Route::get('peticiones/{id}',             [AdminPetitionController::class, 'show']);
    Route::post('peticiones',                 [AdminPetitionController::class, 'store']);
    Route::put('peticiones/{id}',             [AdminPetitionController::class, 'update']);
    Route::patch('peticiones/{id}/estado',    [AdminPetitionController::class, 'cambiarEstado']);
    Route::delete('peticiones/{id}',          [AdminPetitionController::class, 'destroy']);

    // Usuarios CRUD
    Route::get('users',                       [AdminUserController::class, 'index']);
    Route::get('users/{id}',                  [AdminUserController::class, 'show']);
    Route::post('users',                      [AdminUserController::class, 'store']);
    Route::put('users/{id}',                  [AdminUserController::class, 'update']);
    Route::delete('users/{id}',               [AdminUserController::class, 'destroy']);

    // Categorías CRUD
    Route::get('categorias',                  [AdminCategoryController::class, 'index']);
    Route::get('categorias/{id}',             [AdminCategoryController::class, 'show']);
    Route::post('categorias',                 [AdminCategoryController::class, 'store']);
    Route::put('categorias/{id}',             [AdminCategoryController::class, 'update']);
    Route::delete('categorias/{id}',          [AdminCategoryController::class, 'destroy']);
});