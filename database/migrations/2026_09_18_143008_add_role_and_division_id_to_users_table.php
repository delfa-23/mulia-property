<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            ])->default('staff_marketing')->after('email');

            $table->foreignId('division_id')
                ->nullable()
                ->after('role')
                ->constrained('divisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn([
                'division_id',
                'role',
            ]);
        });
    }
};
