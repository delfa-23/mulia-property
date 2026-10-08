<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_stages', function (Blueprint $table) {
            $table->decimal('min_progress', 5, 2)
                ->default(0)
                ->after('description');

            $table->decimal('max_progress', 5, 2)
                ->default(100)
                ->after('min_progress');
        });
    }

    public function down(): void
    {
        Schema::table('construction_stages', function (Blueprint $table) {
            $table->dropColumn([
                'min_progress',
                'max_progress',
            ]);
        });
    }
};
