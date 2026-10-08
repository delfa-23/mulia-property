<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_transaction_id')
                ->constrained('expense_transactions')
                ->cascadeOnDelete();
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('previous_category');
            $table->string('category');
            $table->date('previous_transaction_date');
            $table->date('transaction_date');
            $table->text('previous_description')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_transaction_histories');
    }
};
