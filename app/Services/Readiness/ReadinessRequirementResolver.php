<?php

namespace App\Services\Readiness;

use App\Enums\ReadinessType;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\ReadinessRequirement;
use Illuminate\Database\Eloquent\Collection;

final class ReadinessRequirementResolver
{
    /**
     * @return Collection<int, ReadinessRequirement>
     */
    public function resolve(
        DemandForecast $forecast,
        Organization $organization,
        ReadinessType $readinessType,
    ): Collection {
        return ReadinessRequirement::query()
            ->where(
                'readiness_type',
                $readinessType->value
            )
            ->where(
                'applies_to_organization_type',
                $organization
                    ->organization_type
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                function ($query) use (
                    $forecast,
                ): void {
                    $query
                        ->whereNull(
                            'commodity_id'
                        )
                        ->orWhere(
                            'commodity_id',
                            $forecast->commodity_id
                        );
                }
            )
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            )
            ->get();
    }
}