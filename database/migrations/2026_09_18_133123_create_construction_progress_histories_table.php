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
        Schema::create('construction_progress_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('construction_progress_id')
                ->constrained('construction_progress')
                ->cascadeOnDelete();

            $table->decimal('progress', 5, 2);

            $table->foreignId('updated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_progress_histories');
    }
};
