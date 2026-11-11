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
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->foreignId('property_id')
                ->nullable()
                ->after('domicile')
                ->constrained('properties')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
