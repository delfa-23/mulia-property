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
        Schema::create('finance_cash_account_balance_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_account_id')
                ->nullable()
                ->constrained('finance_cash_accounts')
                ->nullOnDelete();
            $table->string('account_name');
            $table->decimal('previous_balance', 15, 2)->nullable();
            $table->decimal('new_balance', 15, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_cash_account_balance_histories');
    }
};
