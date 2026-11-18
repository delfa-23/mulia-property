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
        Schema::table('booking_payment_schedules', function (Blueprint $table): void {
            $table->foreignId('booking_id')
                ->nullable()
                ->after('property_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->index(
                ['property_id', 'booking_id', 'is_active', 'sort_order'],
                'booking_payment_schedules_booking_scope_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payment_schedules', function (Blueprint $table): void {
            $table->dropIndex('booking_payment_schedules_booking_scope_idx');
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
