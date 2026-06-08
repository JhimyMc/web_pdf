<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srs_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_card_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('card_index'); // posición de la tarjeta dentro del set

            // SM-2 algorithm fields
            $table->float('ease_factor')->default(2.5);   // factor de facilidad (mín 1.3)
            $table->unsignedInteger('interval_days')->default(1); // días hasta próxima revisión
            $table->unsignedInteger('repetitions')->default(0);   // repeticiones exitosas consecutivas
            $table->timestamp('next_review_at')->useCurrent();    // cuándo revisar próxima vez
            $table->timestamp('last_reviewed_at')->nullable();    // última revisión

            $table->timestamps();

            // Un usuario no puede tener duplicados de la misma tarjeta en el mismo set
            $table->unique(['user_id', 'study_card_set_id', 'card_index'], 'uq_srs_card');
            $table->index(['user_id', 'next_review_at'], 'idx_srs_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srs_cards');
    }
};
