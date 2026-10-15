<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_progress', function (Blueprint $table): void {
            $table->decimal('realization', 15, 2)->default(0)->after('progress');
        });

        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->decimal('previous_realization', 15, 2)->nullable()->after('previous_progress');
            $table->decimal('realization', 15, 2)->default(0)->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('construction_progress_histories', function (Blueprint $table): void {
            $table->dropColumn(['previous_realization', 'realization']);
        });

        Schema::table('construction_progress', function (Blueprint $table): void {
            $table->dropColumn('realization');
        });
    }
};
