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
        Schema::create('booking_payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->decimal('amount', 15, 2);
            $table->string('order_id', 50)->unique();
            $table->string('status')->default('pending');
            $table->string('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'stage', 'status']);
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->foreignId('booking_payment_order_id')
                ->nullable()
                ->unique()
                ->constrained('booking_payment_orders')
                ->nullOnDelete();
        });

        Schema::table('finance_cash_flows', function (Blueprint $table): void {
            $table->foreignId('booking_payment_id')
                ->nullable()
                ->unique()
                ->constrained('booking_payments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_cash_flows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('booking_payment_id');
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('booking_payment_order_id');
        });

        Schema::dropIfExists('booking_payment_orders');
    }
};
