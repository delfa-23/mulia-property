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
        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->dropForeign(['construction_progress_id']);
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('construction_stages')->nullOnDelete();
            $table->unsignedBigInteger('construction_progress_id')->nullable()->change();
        });

        DB::table('construction_progress_histories')
            ->whereNotNull('construction_progress_id')
            ->orderBy('id')
            ->select(['id', 'construction_progress_id'])
            ->get()
            ->each(function (object $history): void {
                $progress = DB::table('construction_progress')
                    ->where('id', $history->construction_progress_id)
                    ->first(['lot_id', 'stage_id']);

                if ($progress !== null) {
                    DB::table('construction_progress_histories')
                        ->where('id', $history->id)
                        ->update([
                            'lot_id' => $progress->lot_id,
                            'stage_id' => $progress->stage_id,
                        ]);
                }
            });

        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->foreign('construction_progress_id')
                ->references('id')
                ->on('construction_progress')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('construction_progress_histories')->whereNull('construction_progress_id')->exists()) {
            throw new RuntimeException('Cannot restore cascading construction progress history while deleted progress records have retained history.');
        }

        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->dropForeign(['construction_progress_id']);
            $table->dropForeign(['lot_id']);
            $table->dropForeign(['stage_id']);
            $table->dropColumn(['lot_id', 'stage_id']);
            $table->unsignedBigInteger('construction_progress_id')->nullable(false)->change();
        });

        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->foreign('construction_progress_id')
                ->references('id')
                ->on('construction_progress')
                ->cascadeOnDelete();
        });
    }
};
