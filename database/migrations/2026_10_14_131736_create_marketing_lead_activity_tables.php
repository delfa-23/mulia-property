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
        Schema::create('marketing_lead_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('called_at');
            $table->string('outcome');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'called_at']);
            $table->index(['performed_by', 'called_at']);
        });

        Schema::create('marketing_lead_surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->text('result_notes')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_at', 'status']);
            $table->index(['assigned_user_id', 'scheduled_at']);
        });

        Schema::create('marketing_lead_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_at');
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->text('result_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['assigned_user_id', 'status', 'due_at']);
        });

        Schema::create('marketing_lead_follow_up_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follow_up_id')->constrained('marketing_lead_follow_ups')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['follow_up_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_follow_up_histories');
        Schema::dropIfExists('marketing_lead_follow_ups');
        Schema::dropIfExists('marketing_lead_surveys');
        Schema::dropIfExists('marketing_lead_calls');
    }
};
