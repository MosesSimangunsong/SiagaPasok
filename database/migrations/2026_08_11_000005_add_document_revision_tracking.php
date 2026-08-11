<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'document_records',
            function (Blueprint $table) {
                $table->unsignedInteger(
                    'revision_no'
                )->default(1);
            }
        );

        Schema::table(
            'readiness_items',
            function (Blueprint $table) {
                /*
                 * Snapshot revision Document Record
                 * ketika payload readiness dibekukan.
                 *
                 * Jika document revision berubah,
                 * approved readiness lama otomatis
                 * tidak lagi valid.
                 */
                $table->unsignedInteger(
                    'document_record_revision_no'
                )->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'readiness_items',
            function (Blueprint $table) {
                $table->dropColumn(
                    'document_record_revision_no'
                );
            }
        );

        Schema::table(
            'document_records',
            function (Blueprint $table) {
                $table->dropColumn(
                    'revision_no'
                );
            }
        );
    }
};