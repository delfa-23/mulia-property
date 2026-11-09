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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('unpaid_payment_reason_status')->default('pending')->after('unpaid_payment_reason');
            $table->timestamp('unpaid_payment_reason_reviewed_at')->nullable()->after('unpaid_payment_reason_status');
            $table->foreignId('unpaid_payment_reason_reviewed_by')->nullable()->after('unpaid_payment_reason_reviewed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['unpaid_payment_reason_reviewed_by']);
            $table->dropColumn([
                'unpaid_payment_reason_status',
                'unpaid_payment_reason_reviewed_at',
                'unpaid_payment_reason_reviewed_by',
            ]);
        });
    }
};
