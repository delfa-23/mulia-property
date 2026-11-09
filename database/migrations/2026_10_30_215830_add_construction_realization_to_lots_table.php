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
        Schema::table('lots', function (Blueprint $table) {
            $table->decimal('construction_realization', 15, 2)->default(0)->after('house_price');
        });

        DB::table('construction_progress')
            ->select('lot_id')
            ->selectRaw('SUM(realization) as total_realization')
            ->groupBy('lot_id')
            ->orderBy('lot_id')
            ->each(function (object $row): void {
                DB::table('lots')
                    ->where('id', $row->lot_id)
                    ->update(['construction_realization' => $row->total_realization]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('construction_realization');
        });
    }
};
