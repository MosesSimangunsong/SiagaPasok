<?php

use App\Enums\RecoveryRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'confidence_recovery_requests',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'commitment_id'
                )
                    ->constrained(
                        'supply_commitments'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'commitment_version_id'
                )
                    ->constrained(
                        'commitment_versions'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'status'
                )->default(
                    RecoveryRequestStatus
                        ::PENDING_APPROVAL
                        ->value
                );

                $table->text(
                    'recovery_reason'
                );

                $table->foreignId(
                    'requested_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'requested_at'
                );

                $table->foreignId(
                    'reviewed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'reviewed_at'
                )->nullable();

                $table->text(
                    'review_reason'
                )->nullable();

                $table->index(
                    [
                        'commitment_id',
                        'status',
                    ],
                    'confidence_recovery_requests_scope_index'
                );

                $table->index(
                    [
                        'status',
                        'requested_at',
                    ],
                    'confidence_recovery_requests_queue_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'confidence_recovery_requests'
        );
    }
};