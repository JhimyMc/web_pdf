<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('code', 5)->unique()->index();
            $table->string('pdf_name')->nullable(); // Para saber de qué PDF salió
            $table->integer('num_questions')->default(10); // NUEVO: Cantidad de preguntas
            $table->string('difficulty')->default('intermedio'); // NUEVO: Dificultad (basico, intermedio, avanzado)
            $table->json('questions')->nullable();
            $table->enum('status', ['configurando', 'generando', 'espera', 'en_vivo', 'finalizado'])->default('configurando'); // NUEVO: Estado 'configurando'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
