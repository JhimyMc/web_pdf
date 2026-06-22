<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_responses', function (Blueprint $table) {
            $table->boolean('is_kicked')->default(false)->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('student_responses', function (Blueprint $table) {
            $table->dropColumn('is_kicked');
        });
    }
};
