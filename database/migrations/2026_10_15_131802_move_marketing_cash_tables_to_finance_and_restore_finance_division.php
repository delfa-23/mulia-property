<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $financeDivisionId = DB::table('divisions')->where('slug', 'finance')->value('id');
        $legacyDivisionId = DB::table('divisions')->where('slug', 'keuangan')->value('id');

        if ($financeDivisionId !== null && $legacyDivisionId !== null) {
            throw new RuntimeException('Both finance and keuangan divisions exist. Merge them before running this migration.');
        }

        foreach ([
            'marketing_cash_accounts' => 'finance_cash_accounts',
            'marketing_cash_flows' => 'finance_cash_flows',
        ] as $legacyTable => $financeTable) {
            if (Schema::hasTable($legacyTable) && Schema::hasTable($financeTable)) {
                throw new RuntimeException("Both {$legacyTable} and {$financeTable} tables exist. Merge them before running this migration.");
            }

            if (! Schema::hasTable($legacyTable) && ! Schema::hasTable($financeTable)) {
                throw new RuntimeException("Neither {$legacyTable} nor {$financeTable} exists.");
            }
        }

        if ($legacyDivisionId !== null) {
            DB::table('divisions')
                ->where('id', $legacyDivisionId)
                ->update([
                    'name' => 'Finance',
                    'slug' => 'finance',
                    'description' => 'Administrasi dan pengelolaan keuangan.',
                    'updated_at' => now(),
                ]);
            $financeDivisionId = $legacyDivisionId;
        } elseif ($financeDivisionId === null) {
            $financeDivisionId = DB::table('divisions')->insertGetId([
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Administrasi dan pengelolaan keuangan.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereIn('role', ['tl_keuangan', 'tl_finance'])
            ->update([
                'role' => 'tl_finance',
                'division_id' => $financeDivisionId,
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->whereIn('role', ['finance', 'staff_keuangan', 'staff_finance'])
            ->update([
                'role' => 'staff_finance',
                'division_id' => $financeDivisionId,
                'updated_at' => now(),
            ]);

        foreach ([
            'marketing_cash_accounts' => 'finance_cash_accounts',
            'marketing_cash_flows' => 'finance_cash_flows',
        ] as $legacyTable => $financeTable) {
            if (Schema::hasTable($legacyTable)) {
                Schema::rename($legacyTable, $financeTable);
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'finance_cash_accounts' => 'marketing_cash_accounts',
            'finance_cash_flows' => 'marketing_cash_flows',
        ] as $financeTable => $legacyTable) {
            if (Schema::hasTable($financeTable) && Schema::hasTable($legacyTable)) {
                throw new RuntimeException("Both {$financeTable} and {$legacyTable} tables exist. Resolve the conflict before rolling back.");
            }
        }

        foreach ([
            'finance_cash_accounts' => 'marketing_cash_accounts',
            'finance_cash_flows' => 'marketing_cash_flows',
        ] as $financeTable => $legacyTable) {
            if (Schema::hasTable($financeTable)) {
                Schema::rename($financeTable, $legacyTable);
            }
        }

        DB::table('users')
            ->where('role', 'tl_finance')
            ->update(['role' => 'tl_keuangan']);

        DB::table('users')
            ->where('role', 'staff_finance')
            ->update(['role' => 'staff_keuangan']);

        DB::table('divisions')
            ->where('slug', 'finance')
            ->update([
                'name' => 'Keuangan',
                'slug' => 'keuangan',
                'description' => 'Pengelolaan anggaran dan transaksi keuangan.',
                'updated_at' => now(),
            ]);
    }
};
