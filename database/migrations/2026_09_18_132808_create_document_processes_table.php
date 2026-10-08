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
        Schema::create('document_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->unsignedInteger('total_required')->default(0);
            $table->unsignedInteger('total_completed')->default(0);

            $table->enum('status', [
                'incomplete',
                'complete',
                'revision',
            ])->default('incomplete');

            $table->enum('bi_checking_status', [
                'not_checked',
                'processing',
                'approved',
                'rejected',
                'revision',
            ])->default('not_checked');

            $table->foreignId('pic_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_processes');
    }
};
