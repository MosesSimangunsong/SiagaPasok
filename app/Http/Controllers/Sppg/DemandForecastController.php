<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sppg\StoreDemandForecastRequest;
use App\Http\Requests\Sppg\UpdateDemandForecastRequest;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\Unit;
use App\Services\Forecast\DemandForecastService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DemandForecastController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            DemandForecast::class
        );

        $user = request()->user();

        $forecasts = DemandForecast::query()
            ->where(
                'sppg_organization_id',
                $user->organization_id
            )
            ->with([
                'commodity',
                'unit',
            ])
            ->orderByDesc('required_start_at')
            ->orderByDesc('id')
            ->get()
            ->map(
                fn (DemandForecast $forecast) =>
                    $this->serializeForecast($forecast)
            )
            ->values();

        return Inertia::render(
            'Sppg/Forecasts/Index',
            [
                'forecasts' => $forecasts,
            ]
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            DemandForecast::class
        );

        return Inertia::render(
            'Sppg/Forecasts/Create',
            [
                'commodities' => $this->commodityOptions(),
                'units' => $this->unitOptions(),
            ]
        );
    }

    public function store(
        StoreDemandForecastRequest $request,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'create',
            DemandForecast::class
        );

        $forecast = $service->createDraft(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $forecast
            )
            ->with(
                'success',
                'Draft Forecast berhasil dibuat.'
            );
    }

    public function show(
    DemandForecast $forecast,
    ReadyForProcurementEvaluationService
        $readyForProcurementEvaluationService,
): Response {
    Gate::authorize(
        'view',
        $forecast
    );

    $forecast->load([
        'commodity',
        'unit',
        'createdBy',
        'updatedBy',
    ]);

    /*
     * Canonical M09 evaluation.
     *
     * Detail Forecast hanya menerima summary.
     * Contributor detail tetap berada pada
     * /readiness.
     */
    $procurement =
        $readyForProcurementEvaluationService
            ->evaluate(
                $forecast
            );

    return Inertia::render(
        'Sppg/Forecasts/Show',
        [
            'forecast' =>
                $this->serializeForecast(
                    $forecast,
                    true
                ),

            'procurementSummary' => [
                'evaluated_at' =>
                    $procurement
                        ->evaluatedAt
                        ->toIso8601String(),

                'forecast_published' =>
                    $procurement
                        ->forecastPublished,

                'operationally_valid' =>
                    $procurement
                        ->operationallyValid,

                'volume_ready' =>
                    $procurement
                        ->volumeReady,

                'all_contributors_logistics_ready' =>
                    $procurement
                        ->allContributorsLogisticsReady,

                'all_contributors_document_ready' =>
                    $procurement
                        ->allContributorsDocumentReady,

                'contributor_count' =>
                    count(
                        $procurement
                            ->contributorOrganizationIds
                    ),

                'ready_for_procurement' =>
                    $procurement
                        ->readyForProcurement,

                'reason_codes' =>
                    $procurement
                        ->reasonCodes,
            ],
        ]
    );
}

    public function edit(
        DemandForecast $forecast
    ): Response {
        Gate::authorize(
            'updateDraft',
            $forecast
        );

        $forecast->load([
            'commodity',
            'unit',
        ]);

        return Inertia::render(
            'Sppg/Forecasts/Edit',
            [
                'forecast' =>
                    $this->serializeForecast(
                        $forecast,
                        true
                    ),

                'commodities' =>
                    $this->commodityOptions(),

                'units' =>
                    $this->unitOptions(),
            ]
        );
    }

    public function update(
        UpdateDemandForecastRequest $request,
        DemandForecast $forecast,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'updateDraft',
            $forecast
        );

        $data = $request->validated();
        $version = (int) $data['version'];

        unset($data['version']);

        $updated = $service->updateDraft(
            $request->user(),
            $forecast,
            $data,
            $version,
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $updated
            )
            ->with(
                'success',
                'Draft Forecast berhasil diperbarui.'
            );
    }

    private function commodityOptions(): array
    {
        return Commodity::query()
            ->where('is_active', true)
            ->with('defaultUnit')
            ->orderBy('name')
            ->get()
            ->map(fn (Commodity $commodity) => [
                'id' => $commodity->id,
                'code' => $commodity->code,
                'name' => $commodity->name,
                'default_unit_id' =>
                    $commodity->default_unit_id,
                'default_unit_symbol' =>
                    $commodity->defaultUnit?->symbol,
            ])
            ->values()
            ->all();
    }

    private function unitOptions(): array
    {
        return Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'decimal_precision' =>
                    $unit->decimal_precision,
            ])
            ->values()
            ->all();
    }

    private function serializeForecast(
        DemandForecast $forecast,
        bool $withActor = false,
    ): array {
        $data = [
            'id' => $forecast->id,
            'forecast_code' =>
                $forecast->forecast_code,

            'commodity' => [
                'id' => $forecast->commodity->id,
                'code' => $forecast->commodity->code,
                'name' => $forecast->commodity->name,
            ],

            'unit' => [
                'id' => $forecast->unit->id,
                'code' => $forecast->unit->code,
                'name' => $forecast->unit->name,
                'symbol' => $forecast->unit->symbol,
                'decimal_precision' =>
                    $forecast->unit->decimal_precision,
            ],

            'target_volume' =>
                (string) $forecast->target_volume,

            'required_start_at' =>
                $forecast->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast->required_end_at
                    ?->toIso8601String(),

            'freshness_interval_hours' =>
                $forecast->freshness_interval_hours,

            'status' => $forecast->status->value,
            'status_label' =>
                $forecast->status->label(),

            'notes' => $forecast->notes,

            'published_at' =>
                $forecast->published_at
                    ?->toIso8601String(),

            'closed_at' =>
                $forecast->closed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $forecast->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $forecast->cancellation_reason,

            'version' => $forecast->version,

            'can' => [
                'edit_draft' =>
                    request()->user()->can(
                        'updateDraft',
                        $forecast
                    ),

                'publish' =>
                    request()->user()->can(
                        'publish',
                        $forecast
                    ) && $forecast->isDraft(),

                'revise' =>
                    request()->user()->can(
                        'revise',
                        $forecast
                    ) && $forecast->isPublished(),

                'cancel' =>
                    request()->user()->can(
                        'cancel',
                        $forecast
                    ) && (
                        $forecast->isDraft()
                        || $forecast->isPublished()
                    ),

                'close' =>
                    request()->user()->can(
                        'close',
                        $forecast
                    ) && $forecast->isPublished(),
            ],
        ];

        if ($withActor) {
            $data['created_by'] =
                $forecast->createdBy
                    ? [
                        'id' => $forecast->createdBy->id,
                        'name' => $forecast->createdBy->name,
                    ]
                    : null;

            $data['updated_by'] =
                $forecast->updatedBy
                    ? [
                        'id' => $forecast->updatedBy->id,
                        'name' => $forecast->updatedBy->name,
                    ]
                    : null;
        }

        return $data;
    }
}