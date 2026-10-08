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
        DB::table('divisions')->updateOrInsert(
            ['slug' => 'keuangan'],
            [
                'name' => 'Keuangan',
                'description' => 'Pengelolaan anggaran dan transaksi keuangan.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('divisions')->where('slug', 'keuangan')->delete();
    }
};
