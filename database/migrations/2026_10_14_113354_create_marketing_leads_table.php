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
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date_received');
            $table->string('phone', 50)->nullable();
            $table->string('domicile')->nullable();
            $table->foreignId('sales_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('category')->default('Cold');
            $table->string('source');
            $table->text('last_progress')->nullable();
            $table->date('last_contact_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'date_received']);
            $table->index(['source', 'date_received']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
