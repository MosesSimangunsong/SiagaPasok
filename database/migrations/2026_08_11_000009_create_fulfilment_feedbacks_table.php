<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'fulfilment_feedbacks',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'forecast_id'
                    )
                    ->constrained(
                        'demand_forecasts'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreignId(
                        'contributor_organization_id'
                    )
                    ->constrained(
                        'organizations'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreignId(
                        'unit_id'
                    )
                    ->constrained(
                        'units'
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'planned_volume_snapshot',
                    20,
                    6
                );

                $table->decimal(
                    'delivered_volume',
                    20,
                    6
                );

                $table->date(
                    'fulfilment_date'
                );

                $table->string(
                    'result',
                    32
                );

                $table
                    ->text(
                        'reason_note'
                    )
                    ->nullable();

                $table
                    ->foreignId(
                        'recorded_by'
                    )
                    ->constrained(
                        'users'
                    )
                    ->restrictOnDelete();

                $table->timestamp(
                    'recorded_at'
                );

                $table
                    ->timestamp(
                        'created_at'
                    )
                    ->useCurrent();

                $table->unique(
                    [
                        'forecast_id',
                        'contributor_organization_id',
                    ],
                    'fulfilment_feedback_forecast_contributor_unique'
                );

                $table->index(
                    [
                        'contributor_organization_id',
                        'fulfilment_date',
                    ],
                    'fulfilment_feedback_contributor_date_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fulfilment_feedbacks'
        );
    }
};