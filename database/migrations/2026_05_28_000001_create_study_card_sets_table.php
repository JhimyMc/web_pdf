<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_card_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');                          // Nombre del set (ej: "Historia Universal")
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // PDF origen
            $table->string('status')->default('activo');       // activo | archivado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_card_sets');
    }
};
