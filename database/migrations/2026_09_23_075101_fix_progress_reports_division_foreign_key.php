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
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->foreign('division_id')
                ->references('id')
                ->on('divisions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->foreign('division_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();
        });
    }
};
