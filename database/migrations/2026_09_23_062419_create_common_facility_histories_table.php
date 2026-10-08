<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_facility_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('common_facility_id')
                ->constrained('common_facilities')
                ->cascadeOnDelete();

            $table->decimal('previous_progress', 5, 2)
                ->nullable();

            $table->decimal('progress', 5, 2);

            $table->foreignId('updated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('common_facility_histories');
    }
};
