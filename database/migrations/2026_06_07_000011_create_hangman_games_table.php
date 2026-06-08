<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hangman_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedSmallInteger('wrong_guesses')->default(0);
            $table->string('secret_phrase');                         // La frase/respuesta de la tarjeta
            $table->json('guessed_letters')->nullable();             // Letras adivinadas
            $table->boolean('won')->default(false);
            $table->boolean('completed')->default(false);            // Si el juego terminó
            $table->unsignedInteger('xp_earned')->default(0);       // XP ganado en esta partida
            $table->timestamps();

            $table->index(['user_id', 'completed', 'won']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hangman_games');
    }
};
