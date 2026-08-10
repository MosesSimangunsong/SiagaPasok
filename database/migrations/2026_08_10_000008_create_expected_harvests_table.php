<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'expected_harvests',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('organization_id')
                    ->constrained('organizations')
                    ->restrictOnDelete();

                $table->foreignId('producer_id')
                    ->constrained('producers')
                    ->restrictOnDelete();

                $table->foreignId('commodity_id')
                    ->constrained('commodities')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->decimal(
                    'expected_min_volume',
                    18,
                    6
                );

                $table->decimal(
                    'expected_max_volume',
                    18,
                    6
                );

                $table->dateTime(
                    'harvest_start_at'
                );

                $table->dateTime(
                    'harvest_end_at'
                );

                $table->text('notes')
                    ->nullable();

                $table->foreignId('last_updated_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'commodity_id',
                        'harvest_start_at',
                        'harvest_end_at',
                    ],
                    'expected_harvests_scope_index'
                );

                $table->index(
                    [
                        'organization_id',
                        'producer_id',
                        'harvest_start_at',
                    ],
                    'expected_harvests_producer_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'expected_harvests'
        );
    }
};