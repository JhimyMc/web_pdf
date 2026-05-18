<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\Api\DocumentController as ApiDocumentController;
use App\Http\Controllers\DocumentController as WebDocumentController;

Route::post('/chat/ask-public', [ApiDocumentController::class, 'apiAskPublic']);
Route::post('/chat/ask-app', [ApiDocumentController::class, 'apiAskPublic']);

Route::get('/documentos/{id}/historial', [ApiDocumentController::class, 'apiObtenerHistorial']);

Route::post('/docente/subir-pdf', [ApiDocumentController::class, 'apiSubirPdfDocente']);
Route::get('/docente/pdfs', [ApiDocumentController::class, 'apiObtenerPdfsDocente']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/documents/upload-app', [WebDocumentController::class, 'uploadApp']);
Route::get('/docente/{user_id}/pdfs', [WebDocumentController::class, 'obtenerPdfsDocenteApp']);

Route::get('/rooms/{code}/status', [QuizController::class, 'apiGetStatus']);
Route::post('/responses/send', [QuizController::class, 'apiSaveResponse']);
Route::post('/rooms/webhook-n8n', [QuizController::class, 'apiWebhookN8n']);

Route::post('/rooms/create-from-app', [QuizController::class, 'apiCrearSalaDesdeApp']);
Route::get('/rooms/{code}/status-app', [QuizController::class, 'apiObtenerEstadoSala']);
Route::post('/rooms/save-response', [QuizController::class, 'apiGuardarRespuestaApp']);
Route::post('/rooms/{code}/start', [QuizController::class, 'apiStartRoom']);
Route::post('/rooms/{code}/end', [QuizController::class, 'apiEndRoom']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});
