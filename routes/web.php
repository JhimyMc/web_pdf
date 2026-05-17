<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

// Vista de inicio (Landing / Dashboard unificado cargando tus PDFs de la Base de Datos)
Route::get('/', [DocumentController::class, 'index'])->name('dashboard');

// Área totalmente pública para los alumnos (Ingreso al examen por código sin requerir cuenta)
Route::view('/modo-examen', 'modo-examen')->name('modo.examen');


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

    // 🚀 PETICIONES ASÍNCRONAS (AJAX / FETCH): Comparten la sesión del docente logueado
    Route::post('/ajax/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/ajax/documents/{id}/messages', [DocumentController::class, 'messages']);
    Route::delete('/documentos/{id}', [DocumentController::class, 'destroy'])->name('documentos.destroy');
    Route::post('/ajax/chat/ask', [DocumentController::class, 'ask']);
});


// Archivo de autenticación por defecto de Laravel (Breeze/Jetstream)
require __DIR__ . '/auth.php';
