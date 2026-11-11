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
        if (! Schema::hasTable('booking_payment_schedules')) {
            Schema::create('booking_payment_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('title', 100);
                $table->decimal('amount', 15, 2);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['property_id', 'is_active', 'sort_order']);
            });
        }

        foreach (['booking_payments', 'booking_payment_orders'] as $tableName) {
            Schema::whenTableDoesntHaveColumn(
                $tableName,
                'booking_payment_schedule_id',
                fn (Blueprint $table) => $table->unsignedBigInteger('booking_payment_schedule_id')->nullable(),
            );

            if (! Schema::hasForeignKey($tableName, ['booking_payment_schedule_id'])) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreign('booking_payment_schedule_id')
                        ->references('id')
                        ->on('booking_payment_schedules')
                        ->nullOnDelete();
                });
            }
        }

        Schema::whenTableDoesntHaveIndex(
            'booking_payments',
            'booking_payments_schedule_status_idx',
            fn (Blueprint $table) => $table->index(
                ['booking_id', 'booking_payment_schedule_id', 'approval_status'],
                'booking_payments_schedule_status_idx',
            ),
        );

        Schema::whenTableDoesntHaveIndex(
            'booking_payment_orders',
            'booking_payment_orders_schedule_status_idx',
            fn (Blueprint $table) => $table->index(
                ['booking_id', 'booking_payment_schedule_id', 'status'],
                'booking_payment_orders_schedule_status_idx',
            ),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payment_orders', function (Blueprint $table): void {
            $table->dropIndex('booking_payment_orders_schedule_status_idx');
            $table->dropConstrainedForeignId('booking_payment_schedule_id');
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropIndex('booking_payments_schedule_status_idx');
            $table->dropConstrainedForeignId('booking_payment_schedule_id');
        });

        Schema::dropIfExists('booking_payment_schedules');
    }
};
