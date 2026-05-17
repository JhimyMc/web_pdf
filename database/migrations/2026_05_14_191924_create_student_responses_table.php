<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_responses', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 5)->index(); // Foreign key conceptual
            $table->string('student_name');
            $table->integer('question_index');
            $table->integer('selected_option'); // Del 0 al 4
            $table->boolean('is_correct')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_responses');
    }
};
