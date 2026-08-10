<?php

use App\Enums\FallbackRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'fallback_requests',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'forecast_id'
                )
                    ->constrained(
                        'demand_forecasts'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'requester_organization_id'
                )
                    ->constrained(
                        'organizations'
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'requested_volume',
                    18,
                    6
                );

                $table->foreignId(
                    'unit_id'
                )
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->dateTime(
                    'response_deadline_at'
                );

                $table->string(
                    'status'
                )->default(
                    FallbackRequestStatus
                        ::DRAFT
                        ->value
                );

                /*
                 * Broadcast-safe aggregate note only.
                 * Producer / Expected Harvest /
                 * Commitment internals must never
                 * be copied into this record.
                 */
                $table->text(
                    'broadcast_note'
                )->nullable();

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

                $table->dateTime(
                    'opened_at'
                )->nullable();

                $table->dateTime(
                    'fulfilled_at'
                )->nullable();

                $table->dateTime(
                    'cancelled_at'
                )->nullable();

                $table->text(
                    'cancellation_reason'
                )->nullable();

                $table->dateTime(
                    'expired_at'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'forecast_id',
                        'status',
                        'response_deadline_at',
                    ],
                    'fallback_requests_forecast_status_deadline_index'
                );

                $table->index(
                    [
                        'requester_organization_id',
                        'status',
                    ],
                    'fallback_requests_requester_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fallback_requests'
        );
    }
};