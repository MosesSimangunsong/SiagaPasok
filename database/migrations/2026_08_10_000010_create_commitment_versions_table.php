<?php

use App\Enums\CommitmentApprovalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commitment_versions',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'commitment_id'
                )
                    ->constrained(
                        'supply_commitments'
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'version_no'
                );

                $table->decimal(
                    'min_volume',
                    18,
                    6
                );

                $table->decimal(
                    'max_volume',
                    18,
                    6
                );

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->dateTime(
                    'availability_start_at'
                );

                $table->dateTime(
                    'availability_end_at'
                );

                $table->text(
    'notes'
)->nullable();

                $table->string(
                    'approval_status'
                )->default(
                    CommitmentApprovalStatus
                        ::DRAFT
                        ->value
                );

                $table->text(
                    'change_reason'
                )->nullable();

                $table->text(
                    'operator_justification'
                )->nullable();

                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('submitted_by')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'submitted_at'
                )->nullable();

                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'reviewed_at'
                )->nullable();

                $table->text(
                    'review_reason'
                )->nullable();

                $table->dateTime(
                    'approved_at'
                )->nullable();

                /*
                 * Immutable historical payload:
                 * ERD hanya membutuhkan created_at.
                 */
                $table->dateTime('created_at');

                $table->unique(
                    [
                        'commitment_id',
                        'version_no',
                    ],
                    'commitment_versions_number_unique'
                );

                $table->index(
                    [
                        'commitment_id',
                        'approval_status',
                    ],
                    'commitment_versions_status_index'
                );

                $table->index(
                    [
                        'approval_status',
                        'submitted_at',
                    ],
                    'commitment_versions_queue_index'
                );
            }
        );

        /*
         * Circular reference diselesaikan setelah
         * commitment_versions tersedia.
         */
        Schema::table(
            'supply_commitments',
            function (Blueprint $table) {
                $table->foreign(
                    'active_version_id'
                )
                    ->references('id')
                    ->on('commitment_versions')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'supply_commitments',
            function (Blueprint $table) {
                $table->dropForeign([
                    'active_version_id',
                ]);
            }
        );

        Schema::dropIfExists(
            'commitment_versions'
        );
    }
};