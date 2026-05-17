<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->text('chunk_text'); // Aquí guardaremos el pedazo de texto
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_chunks');
    }
};
