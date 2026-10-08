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
        Schema::create('bank_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->string('bank_name');

            $table->enum('status', [
                'not_submitted',
                'submitted',
                'processing',
                'approved',
                'rejected',
                'revision',
            ])->default('not_submitted');

            $table->foreignId('pic_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_processes');
    }
};
