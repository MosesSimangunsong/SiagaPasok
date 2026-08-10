<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'readiness_requirements',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'readiness_type'
                );

                $table->string(
                    'requirement_code'
                )->unique();

                $table->string(
                    'label'
                );

                $table->string(
                    'requirement_scope'
                );

                $table->string(
                    'applies_to_organization_type'
                );

                $table->foreignId(
                    'commodity_id'
                )
                    ->nullable()
                    ->constrained(
                        'commodities'
                    )
                    ->restrictOnDelete();

                $table->boolean(
                    'is_required_default'
                )->default(true);

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->unsignedSmallInteger(
                    'sort_order'
                )->default(0);

                /*
                 * Metadata/configuration only.
                 *
                 * Business logic tidak boleh
                 * disembunyikan di dalam JSON.
                 */
                $table->json(
                    'config_json'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'readiness_type',
                        'is_active',
                        'sort_order',
                    ],
                    'readiness_requirements_type_active_order_index'
                );

                $table->index(
                    [
                        'applies_to_organization_type',
                        'readiness_type',
                        'is_active',
                    ],
                    'readiness_requirements_org_type_index'
                );

                $table->index(
                    [
                        'commodity_id',
                        'readiness_type',
                        'is_active',
                    ],
                    'readiness_requirements_commodity_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'readiness_requirements'
        );
    }
};