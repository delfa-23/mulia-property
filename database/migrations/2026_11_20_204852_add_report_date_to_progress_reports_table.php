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
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->date('report_date')->nullable()->after('period_end');
        });

        DB::table('progress_reports')->update([
            'report_date' => DB::raw('COALESCE(DATE(created_at), period_start)'),
        ]);

        Schema::table('progress_reports', function (Blueprint $table) {
            $table->date('report_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropColumn('report_date');
        });
    }
};
