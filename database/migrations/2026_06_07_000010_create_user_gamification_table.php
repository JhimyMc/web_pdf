<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_gamification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);              // Puntos de experiencia totales
            $table->unsignedSmallInteger('level')->default(1);       // Nivel actual
            $table->unsignedInteger('total_reviews')->default(0);    // Total de revisiones completadas
            $table->unsignedInteger('total_mastered')->default(0);   // Total de tarjetas dominadas (interval >= 21)
            $table->unsignedInteger('hangman_wins')->default(0);     // Victorias en ahorcado
            $table->unsignedInteger('current_streak')->default(0);   // Racha actual en días
            $table->unsignedInteger('longest_streak')->default(0);   // Racha más larga
            $table->date('last_active_date')->nullable();            // Última fecha con actividad
            $table->json('achievements')->nullable();                // Logros desbloqueados
            $table->timestamps();

            $table->unique('user_id', 'uq_user_gamification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gamification');
    }
};
