<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Rutas Públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Rutas Protegidas (Solo con sesión iniciada en la App)
Route::middleware('auth:sanctum')->group(function () {
    // Para obtener los datos del usuario actual
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // NUEVA: Para actualizar solo el nombre desde la App
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});