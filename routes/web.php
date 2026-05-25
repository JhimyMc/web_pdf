<?php
// routes/web.php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\MindMapController;
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

    // ── Mapas mentales ──────────────────────────────────────
    Route::get('/mapa-mental',                    [MindMapController::class, 'index'])->name('mapa-mental.index');
    Route::post('/ajax/mapa-mental/generar',      [MindMapController::class, 'generate'])->name('mapa-mental.generate');
    Route::put('/ajax/mapa-mental/{id}',          [MindMapController::class, 'update'])->name('mapa-mental.update');
    Route::delete('/ajax/mapa-mental/{id}',       [MindMapController::class, 'destroy'])->name('mapa-mental.destroy');
    Route::post('/ajax/mapa-mental/upload-rapido', [MindMapController::class, 'uploadRapido'])->name('mapa-mental.uploadRapido');
});

require __DIR__ . '/auth.php';
