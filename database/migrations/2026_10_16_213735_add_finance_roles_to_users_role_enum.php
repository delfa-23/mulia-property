<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'admin',
                'tl_pembangunan',
                'staff_pembangunan',
                'tl_marketing',
                'staff_marketing',
                'tl_pemberkasan',
                'staff_pemberkasan',
                'tl_finance',
                'staff_finance',
            ])->default('staff_marketing')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereIn('role', ['tl_finance', 'staff_finance'])->exists()) {
            throw new RuntimeException('Finance users exist; reassign their roles before rolling back this migration.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'admin',
                'tl_pembangunan',
                'staff_pembangunan',
                'tl_marketing',
                'staff_marketing',
                'tl_pemberkasan',
                'staff_pemberkasan',
            ])->default('staff_marketing')->change();
        });
    }
};
