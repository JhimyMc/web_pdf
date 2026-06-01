<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_card_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_card_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('card_index'); // posición de la tarjeta dentro del set
            $table->timestamp('reviewed_at')->useCurrent();
            $table->timestamps();

            // Un usuario no puede tener duplicados del mismo card_index en el mismo set
            $table->unique(['user_id', 'study_card_set_id', 'card_index'], 'uq_card_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_card_reviews');
    }
};
