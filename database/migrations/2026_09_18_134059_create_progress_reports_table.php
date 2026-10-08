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
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('division_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('period_start');

            $table->date('period_end');

            $table->string('title');

            $table->text('content')->nullable();

            $table->decimal('progress', 5, 2)->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'revision',
                'approved',
            ])->default('draft');

            $table->dateTime('submitted_at')->nullable();

            $table->dateTime('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};
