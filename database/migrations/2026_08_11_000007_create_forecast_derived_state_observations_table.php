<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'forecast_derived_state_observations',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('forecast_id')
                    ->constrained(
                        'demand_forecasts'
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'forecast_version'
                );

                $table->decimal(
                    'demand_target',
                    20,
                    6
                );

                $table
                    ->decimal(
                        'total_safe_supply',
                        20,
                        6
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'shortfall',
                        20,
                        6
                    )
                    ->nullable();

                $table->boolean(
                    'ready_for_procurement'
                );

                /*
                 * Historical contributor snapshot
                 * dibutuhkan terutama ketika RFP
                 * TRUE -> FALSE dan contributor
                 * yang bermasalah keluar dari
                 * current effective set.
                 */
                $table->json(
                    'contributor_organization_ids'
                );

                $table->json(
                    'reason_codes'
                );

                /*
                 * Waktu canonical M09 evaluation.
                 */
                $table->timestamp(
                    'evaluated_at'
                );

                /*
                 * Waktu snapshot ditulis.
                 */
                $table->timestamp(
                    'created_at'
                );

                $table->index(
                    [
                        'forecast_id',
                        'evaluated_at',
                        'id',
                    ],
                    'forecast_derived_observation_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'forecast_derived_state_observations'
        );
    }
};