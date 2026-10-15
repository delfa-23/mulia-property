<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('marketing_leads')
            ->where('category', 'Cold')
            ->update(['category' => 'Cool']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep converted lead categories intact when rolling back unrelated schema changes.
    }
};
