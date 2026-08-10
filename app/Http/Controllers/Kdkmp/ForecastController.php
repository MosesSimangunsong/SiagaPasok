<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Http\Controllers\Controller;
use App\Models\SupplyCommitment;
use App\Models\DemandForecast;
use App\Models\SupplyNetworkLink;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(
            'viewKdkmpIndex',
            DemandForecast::class
        );

        $user = request()->user();

        $sppgIds = SupplyNetworkLink::query()
            ->where(
                'kdkmp_organization_id',
                $user->organization_id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->whereHas(
                'sppgOrganization',
                fn ($query) =>
                    $query->where(
                        'is_active',
                        true
                    )
            )
            ->pluck('sppg_organization_id');

        $forecasts = DemandForecast::query()
            ->whereIn(
                'sppg_organization_id',
                $sppgIds
            )
            ->where(
                'status',
                ForecastStatus::PUBLISHED->value
            )
            ->with([
                'sppgOrganization',
                'commodity',
                'unit',
            ])
            ->orderBy('required_start_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (DemandForecast $forecast) =>
                    $this->serializeForecast($forecast)
            )
            ->values();

        return Inertia::render(
            'Kdkmp/Forecasts/Index',
            [
                'forecasts' => $forecasts,
            ]
        );
    }

    public function show(
        DemandForecast $forecast
    ): Response {
        Gate::authorize(
            'view',
            $forecast
        );

        $forecast->load([
            'sppgOrganization',
            'commodity',
            'unit',
        ]);

        return Inertia::render(
            'Kdkmp/Forecasts/Show',
            [
    'forecast' =>
        $this->serializeForecast(
            $forecast
        ),

    'canCreateCommitment' =>
        request()
            ->user()
            ->can(
                'create',
                SupplyCommitment::class
            ),
]
        );
    }

    private function serializeForecast(
        DemandForecast $forecast
    ): array {
        return [
            'id' => $forecast->id,
            'forecast_code' =>
                $forecast->forecast_code,

            'sppg' => [
                'id' =>
                    $forecast->sppgOrganization->id,
                'code' =>
                    $forecast->sppgOrganization->code,
                'name' =>
                    $forecast->sppgOrganization->name,
                'general_location' =>
                    $forecast
                        ->sppgOrganization
                        ->general_location,
            ],

            'commodity' => [
                'id' => $forecast->commodity->id,
                'code' => $forecast->commodity->code,
                'name' => $forecast->commodity->name,
            ],

            'target_volume' =>
                (string) $forecast->target_volume,

            'unit' => [
                'id' => $forecast->unit->id,
                'name' => $forecast->unit->name,
                'symbol' => $forecast->unit->symbol,
                'decimal_precision' =>
                    $forecast->unit->decimal_precision,
            ],

            'required_start_at' =>
                $forecast->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast->required_end_at
                    ?->toIso8601String(),

            'freshness_interval_hours' =>
                $forecast->freshness_interval_hours,

            'status' =>
                $forecast->status->value,

            'status_label' =>
                $forecast->status->label(),

            'notes' => $forecast->notes,

            'published_at' =>
                $forecast->published_at
                    ?->toIso8601String(),
        ];
    }
}