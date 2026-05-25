<?php
// C:\laragon\www\web-pdf\database\migrations\2026_05_24_000001_create_mind_maps_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mind_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');                    // Título/tema del mapa mental
            $table->text('prompt_original');            // La pregunta/tema que escribió el usuario
            $table->longText('map_data');               // JSON con la estructura de nodos del mapa
            $table->string('status')->default('activo'); // activo | archivado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mind_maps');
    }
};
