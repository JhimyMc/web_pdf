<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\Api\ApiQuizController;
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
Route::delete('/docente/documentos/{id}', [\App\Http\Controllers\Api\ApiDocumentController::class, 'apiEliminarDocumento']);

// ══════════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════════
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Firebase Google Sign-In (para la app Android)
Route::post('/auth/google', [\App\Http\Controllers\Api\FirebaseAuthController::class, 'loginWithGoogle']);

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

// Unirse a sala desde Android
Route::post('/rooms/{code}/join',  [ApiQuizController::class, 'apiJoinRoom']);

// Webhook n8n (generación de preguntas IA)
Route::post('/rooms/webhook-n8n',  [QuizController::class, 'apiWebhookN8n']);

// ══════════════════════════════════════════════════════════════
// SALAS — endpoints de la app móvil del DOCENTE
// (inicio/fin de sala: no usan la sesión web de Laravel,
//  se autentican con Sanctum en el grupo de abajo)
// ══════════════════════════════════════════════════════════════
Route::post('/rooms/create-from-app',       [ApiQuizController::class, 'apiCrearSalaDesdeApp']);
Route::post('/rooms/create-from-document',  [ApiQuizController::class, 'apiCrearSalaDesdeDocumento']);
Route::get('/rooms/{code}/status-app',   [ApiQuizController::class, 'apiObtenerEstadoSala']);
Route::post('/rooms/save-response',      [ApiQuizController::class, 'apiGuardarRespuestaApp']);

// Iniciar / finalizar sala desde la app del docente
// NOTA: estas rutas ya existen en web.php bajo /sala/api/rooms/{code}/start|end
// pero requieren sesión. Las siguientes sirven al cliente Android con Sanctum.
Route::post('/rooms/{code}/start',       [QuizController::class, 'apiStartRoom']);
Route::post('/rooms/{code}/end',         [QuizController::class, 'apiEndRoom']);

// Historial de salas del docente (app móvil)
Route::get('/rooms/history',               [ApiQuizController::class, 'apiHistorialApp']);
Route::get('/rooms/{code}/reporte',        [ApiQuizController::class, 'apiReporteApp']);
Route::delete('/rooms/{code}',             [QuizController::class, 'apiDeleteRoom']);

// ══════════════════════════════════════════════════════════════
// MAPAS MENTALES (app móvil)
// ══════════════════════════════════════════════════════════════
Route::get('/mapa-mental/mis-mapas', [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiObtenerMisMapas']);
Route::post('/mapa-mental/generar',  [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiGenerar']);
Route::get('/mapa-mental/{id}/status', [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiCheckStatus']);
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
// REPETICIÓN ESPACIADA (SRS) — app móvil
// ══════════════════════════════════════════════════════════════
Route::get('/srs/{id}/queue', [\App\Http\Controllers\Api\ApiSrsController::class, 'apiGetReviewQueue']);
Route::post('/srs/sync',      [\App\Http\Controllers\Api\ApiSrsController::class, 'apiSync']);
Route::post('/srs/{id}/review', [\App\Http\Controllers\Api\ApiSrsController::class, 'apiReview']);
Route::get('/srs/stats',      [\App\Http\Controllers\Api\ApiSrsController::class, 'apiStats']);

// ══════════════════════════════════════════════════════════════
// GAMIFICACIÓN (app móvil)
// ══════════════════════════════════════════════════════════════
Route::get('/gamification/stats',       [\App\Http\Controllers\GamificationController::class, 'apiStats']);
Route::get('/gamification/leaderboard', [\App\Http\Controllers\GamificationController::class, 'apiLeaderboard']);
Route::get('/notifications/pending',    [\App\Http\Controllers\GamificationController::class, 'pendingNotifications']);

// ══════════════════════════════════════════════════════════════
// AHORCADO (app móvil)
// ══════════════════════════════════════════════════════════════
Route::get('/ahorcado/difficult-cards', [\App\Http\Controllers\HangmanController::class, 'apiGetDifficultCards']);
Route::post('/ahorcado/start',          [\App\Http\Controllers\HangmanController::class, 'apiStartGame']);
Route::post('/ahorcado/guess',          [\App\Http\Controllers\HangmanController::class, 'apiGuess']);
Route::get('/ahorcado/history',         [\App\Http\Controllers\HangmanController::class, 'apiHistory']);

// ══════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS CON SANCTUM (perfil del usuario)
// ══════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});

// ══════════════════════════════════════════════════════════════
// EXAMEN INDIVIDUAL (app móvil - sin auth:sanctum, usa session)
// ══════════════════════════════════════════════════════════════
Route::post('/solo-exam/crear',            [\App\Http\Controllers\SoloExamController::class, 'apiCrear']);
Route::get('/solo-exam/status/{code}',     [\App\Http\Controllers\SoloExamController::class, 'apiStatus']);
Route::post('/solo-exam/guardar-respuesta', [\App\Http\Controllers\SoloExamController::class, 'apiGuardarRespuesta']);
Route::post('/solo-exam/finalizar/{code}', [\App\Http\Controllers\SoloExamController::class, 'apiFinalizar']);
Route::get('/solo-exam/reporte/{code}',    [\App\Http\Controllers\SoloExamController::class, 'apiReporte']);
Route::get('/solo-exam/historial',         [\App\Http\Controllers\SoloExamController::class, 'apiHistorial']);
