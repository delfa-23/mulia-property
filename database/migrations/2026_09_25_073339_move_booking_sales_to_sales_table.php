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
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
        });

        DB::table('bookings')->update(['sales_id' => null]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('sales_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->foreign('sales_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('bookings')->update(['sales_id' => null]);
    }
};
