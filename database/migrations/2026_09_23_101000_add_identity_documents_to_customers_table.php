<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('birth_place')->nullable()->after('name');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('marital_status')->nullable()->after('birth_date');
            $table->string('occupation')->nullable()->after('marital_status');
            $table->string('ktp_file')->nullable()->after('address');
            $table->string('kk_file')->nullable()->after('ktp_file');
            $table->string('npwp_file')->nullable()->after('kk_file');
            $table->string('booking_form_file')->nullable()->after('npwp_file');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'birth_place',
                'birth_date',
                'marital_status',
                'occupation',
                'ktp_file',
                'kk_file',
                'npwp_file',
                'booking_form_file',
            ]);
        });
    }
};
