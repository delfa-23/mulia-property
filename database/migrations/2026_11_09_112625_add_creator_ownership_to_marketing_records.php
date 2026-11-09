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
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('assigned_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['created_by', 'date_received']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('lead_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['created_by', 'booking_date']);
        });

        DB::table('marketing_leads')
            ->whereNull('created_by')
            ->whereNotNull('assigned_user_id')
            ->update(['created_by' => DB::raw('assigned_user_id')]);

        DB::table('bookings')
            ->whereNull('created_by')
            ->whereNotNull('lead_id')
            ->update([
                'created_by' => DB::raw(
                    '(SELECT created_by FROM marketing_leads WHERE marketing_leads.id = bookings.lead_id)'
                ),
            ]);

        DB::table('bookings')
            ->whereNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $creatorId = DB::table('booking_histories')
                        ->where('booking_id', $booking->id)
                        ->where('to_status', 'booking')
                        ->whereNotNull('changed_by')
                        ->orderBy('changed_at')
                        ->value('changed_by');

                    if ($creatorId !== null) {
                        DB::table('bookings')
                            ->where('id', $booking->id)
                            ->update(['created_by' => $creatorId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['created_by', 'booking_date']);
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropIndex(['created_by', 'date_received']);
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
