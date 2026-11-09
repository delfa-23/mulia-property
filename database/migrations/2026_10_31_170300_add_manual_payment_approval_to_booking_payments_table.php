<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('payment_channel')->default('manual')->after('payment_method');
            $table->string('approval_status')->default('approved')->after('payment_channel');
            $table->string('proof_path')->nullable()->after('approval_status');
            $table->foreignId('reviewed_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->index(['approval_status', 'payment_channel']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropIndex(['approval_status', 'payment_channel']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'payment_channel',
                'approval_status',
                'proof_path',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};
