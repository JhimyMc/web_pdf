<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_card_difficult', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_card_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('card_index');
            $table->timestamps();

            $table->unique(['user_id', 'study_card_set_id', 'card_index'], 'uq_card_difficult');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_card_difficult');
    }
};
