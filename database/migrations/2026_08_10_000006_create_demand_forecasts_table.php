<?php

use App\Enums\ForecastStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sppg_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('commodity_id')
                ->constrained('commodities')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->string('forecast_code')->unique();

            $table->decimal(
                'target_volume',
                18,
                6
            );

            $table->dateTime('required_start_at');
            $table->dateTime('required_end_at');

            $table->unsignedInteger(
                'freshness_interval_hours'
            )->nullable();

            $table->string('status')
                ->default(ForecastStatus::DRAFT->value);

            $table->text('notes')->nullable();

            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->text(
                'cancellation_reason'
            )->nullable();

            $table->unsignedInteger('version')
                ->default(1);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(
                [
                    'sppg_organization_id',
                    'status',
                    'required_start_at',
                ],
                'demand_forecasts_scope_index'
            );

            $table->index([
                'commodity_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_forecasts');
    }
};