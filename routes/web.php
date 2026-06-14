<?php
// routes/web.php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\MindMapController;
use App\Http\Controllers\StudyCardController;
use App\Http\Controllers\SrsController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\HangmanController;
use Illuminate\Support\Facades\Route;

// ══════════════════════════════════════════════════════════════
// PÚBLICAS
// ══════════════════════════════════════════════════════════════

// Landing / Dashboard principal
Route::get('/', [DocumentController::class, 'index'])->name('dashboard');

// Página de entrada al modo examen (alumnos y docentes sin sesión)
Route::view('/modo-examen', 'modo-examen')->name('modo.examen');

// Vista del alumno al jugar (no requiere auth: el alumno no tiene cuenta)
Route::get('/sala/play/{code}', [QuizController::class, 'play'])->name('sala.play');

// ══════════════════════════════════════════════════════════════
// PROTEGIDAS — solo usuarios autenticados (Docentes)
// ══════════════════════════════════════════════════════════════
Route::middleware('auth')->group(function () {

    // Perfil
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // AJAX — documentos y chat
    Route::post('/ajax/documents/upload',          [DocumentController::class, 'upload']);
    Route::get('/ajax/documents/{id}/messages',    [DocumentController::class, 'messages']);
    Route::delete('/documentos/{id}',              [DocumentController::class, 'destroy'])->name('documentos.destroy');
    Route::post('/ajax/chat/ask',                  [DocumentController::class, 'ask']);

    // ── Salas de examen ─────────────────────────────────────
    Route::get('/sala/configurar',       [QuizController::class, 'configurar'])->name('sala.configurar');
    Route::post('/sala/crear',           [QuizController::class, 'crearSala'])->name('sala.crear');
    Route::get('/sala/dashboard/{code}', [QuizController::class, 'dashboard'])->name('sala.dashboard');
    Route::get('/sala/reporte/{code}',   [QuizController::class, 'reporte'])->name('sala.reporte');

    // Historial de salas del docente
    Route::get('/sala/historial',        [QuizController::class, 'historial'])->name('sala.historial');

    // Endpoints AJAX del dashboard del docente
    // (usan la sesión web activa; los equivalentes de la app van por api.php con Sanctum)
    Route::post('/sala/api/rooms/{code}/generate', [QuizController::class, 'apiGenerateQuestions']);
    Route::post('/sala/api/rooms/{code}/start',    [QuizController::class, 'apiStartRoom']);
    Route::post('/sala/api/rooms/{code}/end',      [QuizController::class, 'apiEndRoom']);
    Route::delete('/sala/api/rooms/{code}',        [QuizController::class, 'apiDeleteRoom']);
    Route::get('/sala/api/check-active-room',    [QuizController::class, 'apiCheckActiveRoom']);

    // ── Mapas mentales ──────────────────────────────────────
    Route::get('/mapa-mental',                    [MindMapController::class, 'index'])->name('mapa-mental.index');
    Route::post('/ajax/mapa-mental/generar',      [MindMapController::class, 'generate'])->name('mapa-mental.generate');
    Route::put('/ajax/mapa-mental/{id}',          [MindMapController::class, 'update'])->name('mapa-mental.update');
    Route::delete('/ajax/mapa-mental/{id}',       [MindMapController::class, 'destroy'])->name('mapa-mental.destroy');
    Route::post('/ajax/mapa-mental/upload-rapido', [MindMapController::class, 'uploadRapido'])->name('mapa-mental.uploadRapido');
    Route::get('/ajax/mapa-mental/{id}/status', [MindMapController::class, 'checkStatus'])->name('mapa-mental.status');

    // ── Tarjetas de Estudio ────────────────────────────────────
    Route::get('/tarjetas-estudio',                    [StudyCardController::class, 'index'])->name('tarjetas-estudio.index');
    Route::post('/ajax/tarjetas-estudio/generar',      [StudyCardController::class, 'generate'])->name('tarjetas-estudio.generate');
    Route::get('/ajax/tarjetas-estudio/{id}',          [StudyCardController::class, 'show'])->name('tarjetas-estudio.show');
    Route::post('/ajax/tarjetas-estudio/{id}/reviewed',        [StudyCardController::class, 'reviewed'])->name('tarjetas-estudio.reviewed');
    Route::post('/ajax/tarjetas-estudio/{id}/difficult',       [StudyCardController::class, 'markDifficult'])->name('tarjetas-estudio.markDifficult');
    Route::delete('/ajax/tarjetas-estudio/{id}/difficult',     [StudyCardController::class, 'unmarkDifficult'])->name('tarjetas-estudio.unmarkDifficult');
    Route::delete('/ajax/tarjetas-estudio/{id}',               [StudyCardController::class, 'destroy'])->name('tarjetas-estudio.destroy');

    // ── Repetición Espaciada (SRS) ──────────────────────────────
    Route::get('/repeticion-espaciada',                     [SrsController::class, 'index'])->name('srs.index');
    Route::get('/ajax/srs/{id}/queue',                      [SrsController::class, 'getReviewQueue'])->name('srs.queue');
    Route::post('/ajax/srs/sync',                           [SrsController::class, 'sync'])->name('srs.sync');
    Route::post('/ajax/srs/{id}/review',                    [SrsController::class, 'review'])->name('srs.review');
    Route::get('/ajax/srs/stats',                           [SrsController::class, 'stats'])->name('srs.stats');

    // ── Gamificación ──────────────────────────────────────────────
    Route::get('/ajax/gamification/stats',       [GamificationController::class, 'stats'])->name('gamification.stats');
    Route::get('/ajax/gamification/leaderboard', [GamificationController::class, 'leaderboard'])->name('gamification.leaderboard');
    Route::get('/ajax/notifications/pending',    [GamificationController::class, 'pendingNotifications'])->name('notifications.pending');

    // ── Ahorcado ──────────────────────────────────────────────────
    Route::get('/ahorcado',                          [HangmanController::class, 'index'])->name('ahorcado.index');
    Route::post('/ajax/ahorcado/start',              [HangmanController::class, 'startGame'])->name('ahorcado.start');
    Route::post('/ajax/ahorcado/guess',              [HangmanController::class, 'guess'])->name('ahorcado.guess');
});

// ══════════════════════════════════════════════════════════════
// FIREBASE GOOGLE SIGN-IN (web)
// ══════════════════════════════════════════════════════════════
Route::post('/auth/google', [\App\Http\Controllers\FirebaseAuthWebController::class, 'handleGoogleLogin'])
    ->name('auth.google');

require __DIR__ . '/auth.php';
