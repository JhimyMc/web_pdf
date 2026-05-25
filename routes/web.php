<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\MindMapController;
use Illuminate\Support\Facades\Route;


// Vista de inicio (Landing / Dashboard unificado cargando tus PDFs de la Base de Datos)
Route::get('/', [DocumentController::class, 'index'])->name('dashboard');

// Área totalmente pública para los alumnos (Ingreso al examen por código sin requerir cuenta)

// Área totalmente pública para los participantes
Route::view('/modo-examen', 'modo-examen')->name('modo.examen');
// NUEVA RUTA PARA JUGAR EL EXAMEN:
Route::get('/sala/play/{code}', [App\Http\Controllers\QuizController::class, 'play'])->name('sala.play');
// 🔒 RUTAS PROTEGIDAS: Solo usuarios autenticados (Docentes)
Route::middleware('auth')->group(function () {

    // Gestión del perfil del usuario/docente
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // El docente debe estar autenticado para crear y configurar salas de evaluación
    Route::get('/docente/crear-sala', function () {
        return view('crear-sala');
    })->name('docente.crear-sala');

    // 🚀 PETICIONES ASÍNCRONAS DE BIENVENIDA (AJAX / FETCH)
    Route::post('/ajax/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/ajax/documents/{id}/messages', [DocumentController::class, 'messages']);
    Route::delete('/documentos/{id}', [DocumentController::class, 'destroy'])->name('documentos.destroy');
    Route::post('/ajax/chat/ask', [DocumentController::class, 'ask']);

    // Modo Examen - Vistas de gestión de salas
    Route::get('/sala/configurar', [QuizController::class, 'configurar'])->name('sala.configurar');
    Route::post('/sala/crear', [QuizController::class, 'crearSala'])->name('sala.crear');
    Route::get('/sala/dashboard/{code}', [QuizController::class, 'dashboard'])->name('sala.dashboard');

    // 🛠️ Endpoints del Dashboard del Docente (Movidos aquí para compartir la Sesión Web activa)
    Route::post('/sala/api/rooms/{code}/generate', [QuizController::class, 'apiGenerateQuestions']);
    Route::post('/sala/api/rooms/{code}/start', [QuizController::class, 'apiStartRoom']);
    Route::post('/sala/api/rooms/{code}/end', [QuizController::class, 'apiEndRoom']);
    Route::delete('/sala/api/rooms/{code}', [QuizController::class, 'apiDeleteRoom']);

    Route::get('/mapa-mental', [MindMapController::class, 'index'])->name('mapa-mental.index');
    //Mapa Mental AJAX (requieren sesión)
    Route::post('/ajax/mapa-mental/generar', [MindMapController::class, 'generate'])->name('mapa-mental.generate');
    Route::put('/ajax/mapa-mental/{id}', [MindMapController::class, 'update'])->name('mapa-mental.update');
    Route::delete('/ajax/mapa-mental/{id}', [MindMapController::class, 'destroy'])->name('mapa-mental.destroy');
    Route::post('/ajax/mapa-mental/upload-rapido', [MindMapController::class, 'uploadRapido'])->name('mapa-mental.uploadRapido');
});

require __DIR__ . '/auth.php';
