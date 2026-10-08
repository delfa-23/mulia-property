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
        Schema::table('construction_stages', function (Blueprint $table) {
            $table->dropUnique('construction_stages_name_unique');
            $table->dropUnique('construction_stages_order_unique');
            $table->foreignId('property_id')
                ->nullable()
                ->after('id')
                ->constrained('properties')
                ->cascadeOnDelete();
            $table->unique(['property_id', 'name'], 'construction_stages_property_name_unique');
            $table->unique(['property_id', 'order'], 'construction_stages_property_order_unique');
        });

        $globalStages = DB::table('construction_stages')->whereNull('property_id')->orderBy('id')->get();
        $properties = DB::table('properties')->orderBy('id')->get(['id']);

        foreach ($properties as $property) {
            foreach ($globalStages as $stage) {
                $propertyStageId = DB::table('construction_stages')->insertGetId([
                    'property_id' => $property->id,
                    'name' => $stage->name,
                    'order' => $stage->order,
                    'description' => $stage->description,
                    'min_progress' => $stage->min_progress,
                    'max_progress' => $stage->max_progress,
                    'is_active' => $stage->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('construction_progress')
                    ->where('stage_id', $stage->id)
                    ->whereIn('lot_id', function ($query) use ($property) {
                        $query->select('lots.id')
                            ->from('lots')
                            ->join('blocks', 'blocks.id', '=', 'lots.block_id')
                            ->where('blocks.property_id', $property->id);
                    })
                    ->update(['stage_id' => $propertyStageId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $propertyStages = DB::table('construction_stages')->whereNotNull('property_id')->get();

        foreach ($propertyStages as $stage) {
            $globalStage = DB::table('construction_stages')
                ->whereNull('property_id')
                ->where('name', $stage->name)
                ->where('order', $stage->order)
                ->first(['id']);

            DB::table('construction_progress')
                ->where('stage_id', $stage->id)
                ->update(['stage_id' => $globalStage?->id]);
        }

        DB::table('construction_stages')->whereNotNull('property_id')->delete();

        Schema::table('construction_stages', function (Blueprint $table) {
            $table->dropUnique('construction_stages_property_name_unique');
            $table->dropUnique('construction_stages_property_order_unique');
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
            $table->unique('name', 'construction_stages_name_unique');
            $table->unique('order', 'construction_stages_order_unique');
        });
    }
};
