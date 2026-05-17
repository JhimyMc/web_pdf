<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\DocumentController;

// =========================================================================
// RUTAS PÚBLICAS (Usuarios no autenticados)
// =========================================================================

// Autenticación Inicial
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Docente (Android Studio)
Route::post('/rooms/create', [RoomController::class, 'create']);
Route::get('/rooms/{code}/status', [RoomController::class, 'getStatus']);

// Webhook n8n
Route::post('/rooms/update-questions', [RoomController::class, 'updateQuestions']);

// Alumnos (Web y App)
Route::post('/rooms/{code}/submit', [ResponseController::class, 'store']);
Route::post('/rooms/{code}/flag', [ResponseController::class, 'flag']);


// =========================================================================
// RUTAS PROTEGIDAS
// =========================================================================

// Para la App de Android (Token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});

// Para la Web de PlayDF (Sesión persistente del Navegador)
Route::middleware('auth')->group(function () {
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('api.documents.upload');
    Route::post('/chat/ask', [DocumentController::class, 'askChatbot'])->name('api.chat.ask');
    Route::get('/documents/{id}/messages', [DocumentController::class, 'getMessages'])->name('api.documents.messages');
});
