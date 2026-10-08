<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userEmails = DB::table('users')->pluck('email');
        $legacySaleIds = DB::table('sales')
            ->whereIn('email', $userEmails)
            ->pluck('id');

        DB::table('bookings')
            ->whereIn('sales_id', $legacySaleIds)
            ->update(['sales_id' => null]);

        DB::table('sales')
            ->whereIn('id', $legacySaleIds)
            ->delete();
    }

    public function down(): void {}
};
