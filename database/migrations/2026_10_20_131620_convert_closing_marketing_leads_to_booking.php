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
            ->where('category', 'Closing')
            ->update(['category' => 'Booking']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve converted categories because new Booking leads may have been created since this migration.
    }
};
