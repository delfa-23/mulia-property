<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('payment_category')->unique();
            $table->decimal('booking_fee_amount', 15, 2)->default(0);
            $table->decimal('dp_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('booking_fee_deadline_hours')->default(12);
            $table->unsignedSmallInteger('dp_deadline_days')->default(12);
            $table->timestamps();
        });

        $now = now();
        DB::table('booking_payment_settings')->insert([
            [
                'payment_category' => 'cash',
                'booking_fee_amount' => 0,
                'dp_amount' => 0,
                'booking_fee_deadline_hours' => 12,
                'dp_deadline_days' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'payment_category' => 'kpr',
                'booking_fee_amount' => 0,
                'dp_amount' => 0,
                'booking_fee_deadline_hours' => 12,
                'dp_deadline_days' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payment_settings');
    }
};
