<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\ReadinessType;
use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Services\Readiness\ReadinessEvaluationService;
use App\Services\Supply\SupplyMetricsService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __construct(
        private readonly SupplyMetricsService $supplyMetricsService,
        private readonly ReadinessEvaluationService $readinessEvaluationService,
    ) {
    }

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
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'sppgOrganization',
                fn ($query) =>
                    $query->where(
                        'is_active',
                        true
                    )
            )
            ->pluck(
                'sppg_organization_id'
            );

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
            ->orderBy(
                'required_start_at'
            )
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    DemandForecast $forecast
                ) =>
                    $this->serializeForecast(
                        $forecast
                    )
            )
            ->values();

        return Inertia::render(
            'Kdkmp/Forecasts/Index',
            [
                'forecasts' =>
                    $forecasts,
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

        $user =
            request()->user();

        /*
         * Canonical contributor truth.
         *
         * Jangan derive dari PRIMARY link,
         * Commitment, atau Fallback secara
         * terpisah di controller.
         */
        $metrics =
            $this->supplyMetricsService
                ->calculate(
                    $forecast
                );

        $isContributor =
            collect(
                $metrics
                    ->contributorOrganizationIds
            )
                ->contains(
                    fn ($organizationId): bool =>
                        (int) $organizationId
                        === (int)
                        $user->organization_id
                );

        $currentChecklists =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->get()
                ->keyBy(
                    fn (
                        ReadinessChecklist $checklist
                    ): string =>
                        $checklist
                            ->readiness_type
                            ->value
                );

        $logisticsChecklist =
            $currentChecklists->get(
                ReadinessType::LOGISTICS->value
            );

        $documentChecklist =
            $currentChecklists->get(
                ReadinessType::DOCUMENT->value
            );

        /*
         * Derived readiness tetap dievaluasi
         * backend terhadap current supply,
         * Forecast version, checklist, dan
         * document truth.
         */
        $evaluation =
            $this
                ->readinessEvaluationService
                ->evaluateContributor(
                    $forecast,
                    $user->organization_id
                );

        return Inertia::render(
            'Kdkmp/Forecasts/Show',
            [
                'forecast' =>
                    $this->serializeForecast(
                        $forecast
                    ),

                'canCreateCommitment' =>
                    $user->can(
                        'create',
                        SupplyCommitment::class
                    ),

                'readinessContext' => [
                    'is_contributor' =>
                        $isContributor,

                    'logistics' =>
                        $this->serializeReadinessContext(
                            $user,
                            $logisticsChecklist,
                            $isContributor,
                            $evaluation
                                ->logisticsReady,
                            $evaluation
                                ->logisticsReasonCodes
                        ),

                    'document' =>
                        $this->serializeReadinessContext(
                            $user,
                            $documentChecklist,
                            $isContributor,
                            $evaluation
                                ->documentReady,
                            $evaluation
                                ->documentReasonCodes
                        ),
                ],
            ]
        );
    }

    private function serializeReadinessContext(
        $user,
        ?ReadinessChecklist $checklist,
        bool $isContributor,
        bool $ready,
        array $reasonCodes
    ): array {
        return [
            'checklist_id' =>
                $checklist?->id,

            'version_no' =>
                $checklist?->version_no,

            'status' =>
                $checklist
                    ?->status
                    ?->value,

            'ready' =>
                $ready,

            'reason_codes' =>
                $reasonCodes,

            'can_prepare' =>
                $isContributor
                && $checklist === null
                && $user
                    ->isKdkmpOperator(),

            'can_open' =>
                $checklist !== null
                && $user->can(
                    'view',
                    $checklist
                ),
        ];
    }

    private function serializeForecast(
        DemandForecast $forecast
    ): array {
        return [
            'id' =>
                $forecast->id,

            'forecast_code' =>
                $forecast
                    ->forecast_code,

            'sppg' => [
                'id' =>
                    $forecast
                        ->sppgOrganization
                        ->id,

                'code' =>
                    $forecast
                        ->sppgOrganization
                        ->code,

                'name' =>
                    $forecast
                        ->sppgOrganization
                        ->name,

                'general_location' =>
                    $forecast
                        ->sppgOrganization
                        ->general_location,
            ],

            'commodity' => [
                'id' =>
                    $forecast
                        ->commodity
                        ->id,

                'code' =>
                    $forecast
                        ->commodity
                        ->code,

                'name' =>
                    $forecast
                        ->commodity
                        ->name,
            ],

            'target_volume' =>
                (string)
                $forecast
                    ->target_volume,

            'unit' => [
                'id' =>
                    $forecast
                        ->unit
                        ->id,

                'name' =>
                    $forecast
                        ->unit
                        ->name,

                'symbol' =>
                    $forecast
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $forecast
                        ->unit
                        ->decimal_precision,
            ],

            'required_start_at' =>
                $forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast
                    ->required_end_at
                    ?->toIso8601String(),

            'freshness_interval_hours' =>
                $forecast
                    ->freshness_interval_hours,

            'status' =>
                $forecast
                    ->status
                    ->value,

            'status_label' =>
                $forecast
                    ->status
                    ->label(),

            'notes' =>
                $forecast->notes,

            'published_at' =>
                $forecast
                    ->published_at
                    ?->toIso8601String(),
        ];
    }
}