<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuizController;

// =========================================================================
// RUTAS PÚBLICAS (Alumnos Uniéndose y Webhooks de n8n)
// =========================================================================

// Autenticación desde App Android
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Los alumnos verifican si la sala existe o hacen polling en vivo
Route::get('/rooms/{code}/status', [QuizController::class, 'apiGetStatus']);

// Los alumnos envían respuestas
Route::post('/responses/send', [QuizController::class, 'apiSaveResponse']);

// Webhook para que n8n avise que terminó de generar las preguntas de forma asíncrona
Route::post('/rooms/webhook-n8n', [QuizController::class, 'apiWebhookN8n']);

// Para la App de Android (Token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});


// Rutas para la gestión de Salas desde la App Móvil
Route::post('/rooms/create-from-app', [QuizController::class, 'apiCrearSalaDesdeApp']);
Route::get('/rooms/{code}/status', [QuizController::class, 'apiObtenerEstadoSala']);
Route::post('/rooms/save-response', [QuizController::class, 'apiGuardarRespuestaApp']);

Route::post('/rooms/{code}/start', [QuizController::class, 'apiStartRoom']);
Route::post('/rooms/{code}/end', [QuizController::class, 'apiEndRoom']);
