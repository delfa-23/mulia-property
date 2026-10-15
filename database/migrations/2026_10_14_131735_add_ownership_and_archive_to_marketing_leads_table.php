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
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('sales_id')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->index(['assigned_user_id', 'date_received']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex(['assigned_user_id', 'date_received']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['assigned_user_id', 'archived_at']);
        });
    }
};
