<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_card_set_id')->constrained()->onDelete('cascade');
            $table->text('front');       // Pregunta / concepto (anverso)
            $table->text('back');        // Respuesta / definición (reverso)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_cards');
    }
};
