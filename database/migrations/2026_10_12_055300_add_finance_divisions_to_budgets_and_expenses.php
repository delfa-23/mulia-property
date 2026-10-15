<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->string('purpose')->default('pembangunan')->after('category');
        });

        Schema::table('expense_transactions', function (Blueprint $table): void {
            $table->string('division')->default('pembangunan')->after('facility_id');
        });

        DB::table('expense_transactions')->update(['division' => 'marketing']);
    }

    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table): void {
            $table->dropColumn('division');
        });

        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
