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
// AUTH (sin validación de user_id — aún no existe el usuario)
// ══════════════════════════════════════════════════════════════
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/google', [\App\Http\Controllers\Api\FirebaseAuthController::class, 'loginWithGoogle']);

// ══════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS SIN user_id (no requieren validación)
// ══════════════════════════════════════════════════════════════
Route::get('/rooms/{code}/status', [QuizController::class, 'apiGetStatus']);
Route::post('/responses/send',     [QuizController::class, 'apiSaveResponse']);
Route::post('/rooms/{code}/join',  [ApiQuizController::class, 'apiJoinRoom']);

// ══════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS CON SANCTUM (perfil del usuario)
// ══════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});

// ══════════════════════════════════════════════════════════════
// TODAS LAS RUTAS DEBAJO USAN user_id → Middleware de validación
// Si el user_id no existe en la BD, retorna 404 automáticamente.
// ══════════════════════════════════════════════════════════════
Route::middleware('validate.user_id')->group(function () {

    // CHAT / DOCUMENTOS
    Route::post('/chat/ask-public',  [ApiDocumentController::class, 'apiAskPublic']);
    Route::post('/chat/ask-app',     [ApiDocumentController::class, 'apiAskPublic']);
    Route::get('/documentos/{id}/historial', [ApiDocumentController::class, 'apiObtenerHistorial']);
    Route::post('/docente/subir-pdf', [ApiDocumentController::class, 'apiSubirPdfDocente']);
    Route::get('/docente/pdfs',       [ApiDocumentController::class, 'apiObtenerPdfsDocente']);
    Route::delete('/docente/documentos/{id}', [\App\Http\Controllers\Api\ApiDocumentController::class, 'apiEliminarDocumento']);

    // DOCUMENTOS (app)
    Route::post('/documents/upload-app',      [WebDocumentController::class, 'uploadApp']);
    Route::get('/docente/{user_id}/pdfs',     [WebDocumentController::class, 'obtenerPdfsDocenteApp']);

    // SALAS — endpoints del DOCENTE
    Route::post('/rooms/create-from-app',       [ApiQuizController::class, 'apiCrearSalaDesdeApp']);
    Route::post('/rooms/create-from-document',  [ApiQuizController::class, 'apiCrearSalaDesdeDocumento']);
    Route::get('/rooms/{code}/status-app',   [ApiQuizController::class, 'apiObtenerEstadoSala']);
    Route::post('/rooms/save-response',      [ApiQuizController::class, 'apiGuardarRespuestaApp']);
    Route::post('/rooms/{code}/start',       [QuizController::class, 'apiStartRoom']);
    Route::post('/rooms/{code}/end',         [QuizController::class, 'apiEndRoom']);
    Route::get('/rooms/history',               [ApiQuizController::class, 'apiHistorialApp']);
    Route::get('/rooms/{code}/reporte',        [ApiQuizController::class, 'apiReporteApp']);
    Route::delete('/rooms/{code}',             [QuizController::class, 'apiDeleteRoom']);

    // MAPAS MENTALES
    Route::get('/mapa-mental/mis-mapas', [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiObtenerMisMapas']);
    Route::post('/mapa-mental/generar',  [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiGenerar']);
    Route::get('/mapa-mental/{id}/status', [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiCheckStatus']);
    Route::put('/mapa-mental/{id}',      [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiAutoguardar']);
    Route::delete('/mapa-mental/{id}',   [\App\Http\Controllers\Api\ApiMindMapController::class, 'apiEliminar']);

    // TARJETAS DE ESTUDIO
    Route::get('/tarjetas-estudio/mis-sets', [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiObtenerMisSets']);
    Route::post('/tarjetas-estudio/generar',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiGenerar']);
    Route::get('/tarjetas-estudio/{id}',       [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMostrar']);
    Route::delete('/tarjetas-estudio/{id}',    [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiEliminar']);
    Route::post('/tarjetas-estudio/{id}/reviewed',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMarcarRepasada']);
    Route::post('/tarjetas-estudio/{id}/difficult',  [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiMarcarDificil']);
    Route::delete('/tarjetas-estudio/{id}/difficult', [\App\Http\Controllers\Api\ApiStudyCardController::class, 'apiDesmarcarDificil']);

    // REPETICIÓN ESPACIADA (SRS)
    Route::get('/srs/{id}/queue', [\App\Http\Controllers\Api\ApiSrsController::class, 'apiGetReviewQueue']);
    Route::post('/srs/sync',      [\App\Http\Controllers\Api\ApiSrsController::class, 'apiSync']);
    Route::post('/srs/{id}/review', [\App\Http\Controllers\Api\ApiSrsController::class, 'apiReview']);
    Route::get('/srs/stats',      [\App\Http\Controllers\Api\ApiSrsController::class, 'apiStats']);

    // GAMIFICACIÓN
    Route::get('/gamification/stats',       [\App\Http\Controllers\GamificationController::class, 'apiStats']);
    Route::get('/gamification/leaderboard', [\App\Http\Controllers\GamificationController::class, 'apiLeaderboard']);
    Route::get('/notifications/pending',    [\App\Http\Controllers\GamificationController::class, 'pendingNotifications']);

    // AHORCADO
    Route::get('/ahorcado/difficult-cards', [\App\Http\Controllers\HangmanController::class, 'apiGetDifficultCards']);
    Route::post('/ahorcado/start',          [\App\Http\Controllers\HangmanController::class, 'apiStartGame']);
    Route::post('/ahorcado/guess',          [\App\Http\Controllers\HangmanController::class, 'apiGuess']);
    Route::get('/ahorcado/history',         [\App\Http\Controllers\HangmanController::class, 'apiHistory']);

    // EXAMEN INDIVIDUAL
    Route::post('/solo-exam/crear',            [\App\Http\Controllers\SoloExamController::class, 'apiCrear']);
    Route::get('/solo-exam/status/{code}',     [\App\Http\Controllers\SoloExamController::class, 'apiStatus']);
    Route::post('/solo-exam/guardar-respuesta', [\App\Http\Controllers\SoloExamController::class, 'apiGuardarRespuesta']);
    Route::post('/solo-exam/finalizar/{code}', [\App\Http\Controllers\SoloExamController::class, 'apiFinalizar']);
    Route::post('/solo-exam/marcar-dificil',   [\App\Http\Controllers\SoloExamController::class, 'apiMarcarDificil']);
    Route::get('/solo-exam/reporte/{code}',    [\App\Http\Controllers\SoloExamController::class, 'apiReporte']);
    Route::get('/solo-exam/historial',         [\App\Http\Controllers\SoloExamController::class, 'apiHistorial']);

}); // fin validate.user_id
