<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'forecast_derived_state_observations',
            function (Blueprint $table): void {
                $table
                    ->json(
                        'contributor_safe_supply_by_organization'
                    )
                    ->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'forecast_derived_state_observations',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'contributor_safe_supply_by_organization'
                );
            }
        );
    }
};