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
            $table->foreignId('property_id')
                ->nullable()
                ->after('division_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('lot_id')
                ->nullable()
                ->after('property_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('photo_path')->nullable()->after('content');
            $table->dateTime('photo_archived_at')->nullable()->after('approved_at');

            $table->index(['lot_id', 'status', 'photo_archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropIndex(['lot_id', 'status', 'photo_archived_at']);
            $table->dropConstrainedForeignId('lot_id');
            $table->dropConstrainedForeignId('property_id');
            $table->dropColumn(['photo_path', 'photo_archived_at']);
        });
    }
};
