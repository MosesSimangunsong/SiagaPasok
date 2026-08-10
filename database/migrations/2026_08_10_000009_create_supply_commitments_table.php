<?php

use App\Enums\CommitmentLifecycleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'supply_commitments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('forecast_id')
                    ->constrained(
                        'demand_forecasts'
                    )
                    ->restrictOnDelete();

                $table->foreignId('organization_id')
                    ->constrained('organizations')
                    ->restrictOnDelete();

                $table->foreignId('producer_id')
                    ->constrained('producers')
                    ->restrictOnDelete();

                $table->foreignId(
                    'expected_harvest_id'
                )
                    ->nullable()
                    ->constrained(
                        'expected_harvests'
                    )
                    ->restrictOnDelete();

                $table->foreignId('commodity_id')
                    ->constrained('commodities')
                    ->restrictOnDelete();

                /*
                 * FK ditambahkan setelah
                 * commitment_versions dibuat
                 * untuk menghindari circular
                 * migration dependency.
                 */
                $table->unsignedBigInteger(
                    'active_version_id'
                )->nullable();

                $table->string(
                    'lifecycle_status'
                )->default(
                    CommitmentLifecycleStatus
                        ::ACTIVE
                        ->value
                );

                $table->string(
                    'current_confidence'
                )->nullable();

                $table->dateTime(
                    'last_confidence_verified_at'
                )->nullable();

                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();

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
                    'active_version_id'
                );

                $table->index(
                    [
                        'organization_id',
                        'lifecycle_status',
                    ],
                    'supply_commitments_org_index'
                );

                $table->index(
                    [
                        'forecast_id',
                        'lifecycle_status',
                        'current_confidence',
                    ],
                    'supply_commitments_supply_index'
                );

                $table->index(
                    [
                        'producer_id',
                        'lifecycle_status',
                    ],
                    'supply_commitments_producer_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supply_commitments'
        );
    }
};