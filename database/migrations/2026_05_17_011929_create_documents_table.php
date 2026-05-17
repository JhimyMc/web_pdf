<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema; // <-- Corregido (sin el número 5)

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name'); // Nombre del archivo subido (Ej: Historia_Universal.pdf)
            $table->longText('extracted_text'); // El texto plano del PDF guardado para la IA
            $table->integer('tokens_estimated')->default(0); // Control opcional de consumo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
