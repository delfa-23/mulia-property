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
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('block_id')
                ->constrained('blocks')
                ->cascadeOnDelete();

            $table->string('lot_number');
            $table->decimal('house_price', 15, 2);

            $table->enum('status', [
                'available',
                'blocked',
                'booked',
                'process',
                'akad',
                'finish',
                'cancelled',
            ])->default('available');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['block_id', 'lot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
