<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('construction_progress', function (Blueprint $table): void {
            $table->dropForeign(['stage_id']);
            $table->unsignedBigInteger('stage_id')->nullable()->change();
            $table->foreign('stage_id')
                ->references('id')
                ->on('construction_stages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('construction_progress')->whereNull('stage_id')->exists()) {
            throw new RuntimeException('Cannot restore the required construction stage relation while detached progress records exist.');
        }

        Schema::table('construction_progress', function (Blueprint $table): void {
            $table->dropForeign(['stage_id']);
            $table->unsignedBigInteger('stage_id')->nullable(false)->change();
            $table->foreign('stage_id')
                ->references('id')
                ->on('construction_stages')
                ->restrictOnDelete();
        });
    }
};
