<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('house_price_snapshot', 15, 2)->nullable()->after('booking_date');
            $table->decimal('dp_percentage', 5, 2)->default(0)->after('house_price_snapshot');
            $table->decimal('akad_fee', 15, 2)->default(0)->after('dp_percentage');
        });

        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['house_price_snapshot', 'dp_percentage', 'akad_fee']);
        });
    }
};
