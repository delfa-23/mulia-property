<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketing_cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_key')->unique();
            $table->string('name');
            $table->decimal('balance', 15, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_cash_flows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('direction');
            $table->string('status');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'due_date', 'status']);
        });

        $now = now();

        DB::table('marketing_cash_accounts')->insert([
            ['account_key' => 'bank_bsn', 'name' => 'Bank BSN', 'balance' => null, 'created_at' => $now, 'updated_at' => $now],
            ['account_key' => 'bank_bsi', 'name' => 'Bank BSI', 'balance' => null, 'created_at' => $now, 'updated_at' => $now],
            ['account_key' => 'bank_sumut_syariah', 'name' => 'Bank Sumut Syariah', 'balance' => null, 'created_at' => $now, 'updated_at' => $now],
            ['account_key' => 'petty_cash', 'name' => 'Petty Cash', 'balance' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_cash_flows');
        Schema::dropIfExists('marketing_cash_accounts');
    }
};
