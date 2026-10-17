<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('site_plan_path')->nullable();
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->json('map_coordinates')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('map_coordinates');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('site_plan_path');
        });
    }
};
