<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $marketingDivisionId = DB::table('divisions')
            ->where('slug', 'marketing')
            ->value('id');

        if ($marketingDivisionId === null) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['finance', 'tl_finance', 'staff_finance'])
            ->update([
                'role' => DB::raw("CASE WHEN role = 'tl_finance' THEN 'tl_marketing' ELSE 'staff_marketing' END"),
                'division_id' => $marketingDivisionId,
            ]);
    }

    public function down(): void {}
};
