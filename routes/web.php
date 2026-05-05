<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// La página principal ahora se "llama" dashboard para el sistema interno
Route::get('/', function () {
    return view('welcome');
})->name('dashboard'); 

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/modo-examen', function () {
    return view('modo-examen');
});