<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('exam_results', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // El profesor (dueño)
        $table->string('room_id'); // ID de la sala (Ej: PLAY-777)
        $table->string('pdf_name'); // Nombre del archivo evaluado
        $table->integer('total_questions');
        $table->json('ranking'); // Guardaremos el Top de alumnos y notas en formato JSON
        $table->timestamps(); // Esto nos da la fecha del examen automáticamente
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
