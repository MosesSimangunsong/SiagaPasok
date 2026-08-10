<?php

use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'document_records',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'organizations'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'requirement_id'
                )
                    ->constrained(
                        'readiness_requirements'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'document_name'
                );

                $table->string(
                    'reference_number'
                )->nullable();

                $table->dateTime(
                    'valid_from'
                )->nullable();

                $table->dateTime(
                    'expires_at'
                )->nullable();

                $table->string(
                    'status'
                )->default(
                    DocumentStatus
                        ::PENDING
                        ->value
                );

                $table->text(
                    'notes'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'status',
                        'expires_at',
                    ],
                    'document_records_org_status_expiry_index'
                );

                $table->index(
                    [
                        'requirement_id',
                        'status',
                    ],
                    'document_records_requirement_status_index'
                );
            }
        );

        /*
         * Resolve circular relationship:
         *
         * readiness_items
         *     -> optional document_records
         *
         * document_records
         *     -> readiness_requirements
         */
        Schema::table(
            'readiness_items',
            function (Blueprint $table) {
                $table->foreign(
                    'document_record_id'
                )
                    ->references('id')
                    ->on('document_records')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'readiness_items',
            function (Blueprint $table) {
                $table->dropForeign([
                    'document_record_id',
                ]);
            }
        );

        Schema::dropIfExists(
            'document_records'
        );
    }
};