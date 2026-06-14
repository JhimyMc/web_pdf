<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mind_maps', function (Blueprint $table) {
            $table->integer('chunks_total')->default(0)->after('status');
            $table->integer('chunks_completed')->default(0)->after('chunks_total');
            $table->json('partial_results')->nullable()->after('chunks_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mind_maps', function (Blueprint $table) {
            $table->dropColumn(['chunks_total', 'chunks_completed', 'partial_results']);
        });
    }
};
