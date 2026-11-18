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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('payment_category')->nullable()->after('booking_date')->index();
            $table->dateTime('dp_deadline_at')->nullable()->after('blocking_until');
            $table->dateTime('dp_deadline_alerted_at')->nullable()->after('dp_deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['payment_category']);
            $table->dropColumn(['payment_category', 'dp_deadline_at', 'dp_deadline_alerted_at']);
        });
    }
};
