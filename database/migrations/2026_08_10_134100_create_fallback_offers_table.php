<?php

use App\Enums\FallbackOfferStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'fallback_offers',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'fallback_request_id'
                )
                    ->constrained(
                        'fallback_requests'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_organization_id'
                )
                    ->constrained(
                        'organizations'
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'offered_volume',
                    18,
                    6
                );

                /*
                 * Tetap 0 sampai requester Manager
                 * melakukan Accept.
                 */
                $table->decimal(
                    'accepted_volume',
                    18,
                    6
                )->default(
                    '0.000000'
                );

                $table->foreignId(
                    'unit_id'
                )
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->text(
                    'availability_note'
                )->nullable();

                $table->dateTime(
                    'expires_at'
                );

                $table->string(
                    'status'
                )->default(
                    FallbackOfferStatus
                        ::DRAFT
                        ->value
                );

                $table->foreignId(
                    'created_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'submitted_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'submitted_at'
                )->nullable();

                $table->foreignId(
                    'supplier_reviewed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'supplier_reviewed_at'
                )->nullable();

                $table->text(
                    'supplier_review_reason'
                )->nullable();

                $table->foreignId(
                    'requester_decided_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'requester_decided_at'
                )->nullable();

                $table->text(
                    'requester_decision_reason'
                )->nullable();

                $table->foreignId(
                    'withdrawn_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'withdrawn_at'
                )->nullable();

                $table->text(
                    'withdrawal_reason'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'fallback_request_id',
                        'status',
                        'supplier_organization_id',
                    ],
                    'fallback_offers_request_status_supplier_index'
                );

                $table->index(
                    [
                        'supplier_organization_id',
                        'status',
                        'expires_at',
                    ],
                    'fallback_offers_supplier_status_expiry_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fallback_offers'
        );
    }
};