<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\Api\DocumentController as ApiDocumentController;
use App\Http\Controllers\DocumentController as WebDocumentController;

// ══════════════════════════════════════════════════════════════
// CHAT / DOCUMENTOS (sin auth: público para la app móvil)
// ══════════════════════════════════════════════════════════════
Route::post('/chat/ask-public',  [ApiDocumentController::class, 'apiAskPublic']);
Route::post('/chat/ask-app',     [ApiDocumentController::class, 'apiAskPublic']);

Route::get('/documentos/{id}/historial', [ApiDocumentController::class, 'apiObtenerHistorial']);

Route::post('/docente/subir-pdf', [ApiDocumentController::class, 'apiSubirPdfDocente']);
Route::get('/docente/pdfs',       [ApiDocumentController::class, 'apiObtenerPdfsDocente']);

// ══════════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════════
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ══════════════════════════════════════════════════════════════
// DOCUMENTOS (app)
// ══════════════════════════════════════════════════════════════
Route::post('/documents/upload-app',      [WebDocumentController::class, 'uploadApp']);
Route::get('/docente/{user_id}/pdfs',     [WebDocumentController::class, 'obtenerPdfsDocenteApp']);

// ══════════════════════════════════════════════════════════════
// SALAS — endpoints públicos (alumnos sin sesión web)
// ══════════════════════════════════════════════════════════════

// Estado de sala (polling desde Android y desde la web del alumno)
Route::get('/rooms/{code}/status', [QuizController::class, 'apiGetStatus']);

// Envío de respuestas del alumno
Route::post('/responses/send',     [QuizController::class, 'apiSaveResponse']);

// ✅ ENDPOINT QUE FALTABA: unirse a sala desde Android
// El alumno llama a este endpoint al pulsar "Ingresar" para registrar
// su presencia antes de que el polling comience.
Route::post('/rooms/{code}/join',  [QuizController::class, 'apiJoinRoom']);

// Webhook n8n (generación de preguntas IA)
Route::post('/rooms/webhook-n8n',  [QuizController::class, 'apiWebhookN8n']);

// ══════════════════════════════════════════════════════════════
// SALAS — endpoints de la app móvil del DOCENTE
// (inicio/fin de sala: no usan la sesión web de Laravel,
//  se autentican con Sanctum en el grupo de abajo)
// ══════════════════════════════════════════════════════════════
Route::post('/rooms/create-from-app',    [QuizController::class, 'apiCrearSalaDesdeApp']);
Route::get('/rooms/{code}/status-app',   [QuizController::class, 'apiObtenerEstadoSala']);
Route::post('/rooms/save-response',      [QuizController::class, 'apiGuardarRespuestaApp']);

// Iniciar / finalizar sala desde la app del docente
// NOTA: estas rutas ya existen en web.php bajo /sala/api/rooms/{code}/start|end
// pero requieren sesión. Las siguientes sirven al cliente Android con Sanctum.
Route::post('/rooms/{code}/start',       [QuizController::class, 'apiStartRoom']);
Route::post('/rooms/{code}/end',         [QuizController::class, 'apiEndRoom']);

// ══════════════════════════════════════════════════════════════
// MAPAS MENTALES (app móvil)
// ══════════════════════════════════════════════════════════════
Route::get('/mapa-mental/mis-mapas', [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiObtenerMisMapas']);
Route::post('/mapa-mental/generar',  [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiGenerar']);
Route::put('/mapa-mental/{id}',      [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiAutoguardar']);
Route::delete('/mapa-mental/{id}',   [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiEliminar']);

// ══════════════════════════════════════════════════════════════
// TARJETAS DE ESTUDIO (app móvil)
// ══════════════════════════════════════════════════════════════
Route::get('/tarjetas-estudio/mis-sets', [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiObtenerMisSets']);
Route::post('/tarjetas-estudio/generar',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiGenerar']);
Route::get('/tarjetas-estudio/{id}',       [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMostrar']);
Route::delete('/tarjetas-estudio/{id}',    [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiEliminar']);
Route::post('/tarjetas-estudio/{id}/reviewed',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMarcarRepasada']);
Route::post('/tarjetas-estudio/{id}/difficult',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMarcarDificil']);
Route::delete('/tarjetas-estudio/{id}/difficult', [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiDesmarcarDificil']);

// ══════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS CON SANCTUM (perfil del usuario)
// ══════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});
