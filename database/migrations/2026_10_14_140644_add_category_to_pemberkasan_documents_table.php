<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pemberkasan_documents', 'document_category')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->string('document_category')->default('standard')->after('document_type');
            });
        }

        if (! Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_id_index')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->index('booking_id', 'pemberkasan_documents_booking_id_index');
            });
        }

        if (Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_type_unique')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->dropUnique('pemberkasan_documents_booking_type_unique');
            });
        }

        if (! Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_category_type_unique')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->unique(
                    ['booking_id', 'document_category', 'document_type'],
                    'pemberkasan_documents_booking_category_type_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_category_type_unique')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->dropUnique('pemberkasan_documents_booking_category_type_unique');
            });
        }

        if (Schema::hasColumn('pemberkasan_documents', 'document_category')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->dropColumn('document_category');
            });
        }

        if (! Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_type_unique')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->unique(['booking_id', 'document_type'], 'pemberkasan_documents_booking_type_unique');
            });
        }

        if (Schema::hasIndex('pemberkasan_documents', 'pemberkasan_documents_booking_id_index')) {
            Schema::table('pemberkasan_documents', function (Blueprint $table): void {
                $table->dropIndex('pemberkasan_documents_booking_id_index');
            });
        }
    }
};
