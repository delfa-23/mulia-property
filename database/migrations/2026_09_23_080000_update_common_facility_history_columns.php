<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_facility_histories', function (Blueprint $table) {
            $table->renameColumn('previous_progress', 'previous_realization');
            $table->renameColumn('progress', 'realization');
        });
    }

    public function down(): void
    {
        Schema::table('common_facility_histories', function (Blueprint $table) {
            $table->renameColumn('previous_realization', 'previous_progress');
            $table->renameColumn('realization', 'progress');
        });
    }
};
