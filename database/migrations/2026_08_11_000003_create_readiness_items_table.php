<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'readiness_items',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'readiness_checklist_id'
                )
                    ->constrained(
                        'readiness_checklists'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'requirement_id'
                )
                    ->constrained(
                        'readiness_requirements'
                    )
                    ->restrictOnDelete();

                /*
                 * Snapshot dari requirement master.
                 * Perubahan master di masa depan
                 * tidak mengubah checklist history.
                 */
                $table->boolean(
                    'is_required'
                );

                $table->boolean(
                    'is_satisfied'
                )->default(false);

                $table->text(
                    'note'
                )->nullable();

                /*
                 * FK ditambahkan setelah
                 * document_records dibuat untuk
                 * menghindari circular migration
                 * dependency.
                 */
                $table->unsignedBigInteger(
                    'document_record_id'
                )->nullable();

                /*
                 * Optional structured evidence/value.
                 * Tidak menjadi tempat executable
                 * business logic.
                 */
                $table->json(
                    'value_json'
                )->nullable();

                $table->foreignId(
                    'updated_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'readiness_checklist_id',
                        'requirement_id',
                    ],
                    'readiness_items_checklist_requirement_unique'
                );

                $table->index(
                    [
                        'readiness_checklist_id',
                        'is_required',
                        'is_satisfied',
                    ],
                    'readiness_items_evaluation_index'
                );

                $table->index(
                    'document_record_id'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'readiness_items'
        );
    }
};