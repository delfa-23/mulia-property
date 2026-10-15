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
        Schema::table('expense_transactions', function (Blueprint $table): void {
            $table->foreignId('budget_id')
                ->nullable()
                ->after('division')
                ->constrained('budgets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('budget_id');
        });
    }
};
