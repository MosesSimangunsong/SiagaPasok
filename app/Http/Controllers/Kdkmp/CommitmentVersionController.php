<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\SubmitCommitmentVersionRequest;
use App\Http\Requests\Kdkmp\UpdateCommitmentDraftRequest;
use App\Models\CommitmentVersion;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Services\Commitment\CommitmentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentVersionController extends Controller
{
    public function __construct(
        private readonly CommitmentWorkflowService $workflowService
    ) {
    }

    public function edit(
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): Response {
        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        Gate::authorize(
            'updateDraft',
            $version
        );

        $commitment->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',
            'producer',
            'expectedHarvest',
        ]);

        $version->load('unit');

        $isInitialDraft =
            $version->version_no === 1
            && $commitment
                ->active_version_id === null;

        return Inertia::render(
            'Kdkmp/Commitments/Edit',
            [
                'commitment' => [
                    'id' =>
                        $commitment->id,

                    'forecast' =>
                        $this->serializeForecast(
                            $commitment
                        ),

                    'producer_id' =>
                        $commitment
                            ->producer_id,

                    'expected_harvest_id' =>
                        $commitment
                            ->expected_harvest_id,
                ],

                'version' =>
                    $this->serializeVersion(
                        $version
                    ),

                'isInitialDraft' =>
                    $isInitialDraft,

                ...(
                    $isInitialDraft
                        ? $this->sourceOptions(
                            request()
                                ->user()
                                ->organization_id
                        )
                        : []
                ),
            ]
        );
    }

    public function update(
        UpdateCommitmentDraftRequest $request,
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): RedirectResponse {
        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        $this->workflowService->updateDraft(
            $request->user(),
            $version,
            $request->validated()
        );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Draft komitmen berhasil diperbarui.'
            );
    }

    public function submit(
        SubmitCommitmentVersionRequest $request,
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): RedirectResponse {
        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        $this->workflowService->submit(
            $request->user(),
            $version
        );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Komitmen berhasil diajukan kepada Manager.'
            );
    }

    private function assertVersionBelongsToCommitment(
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): void {
        abort_if(
            $version->commitment_id
            !== $commitment->id,
            404
        );
    }

    private function sourceOptions(
        int $organizationId
    ): array {
        $producers =
            Producer::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'producer_code',
                    'name',
                    'village',
                    'district',
                ]);

        $expectedHarvests =
            ExpectedHarvest::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->whereHas(
                    'producer',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->with([
                    'producer',
                    'commodity',
                    'unit',
                ])
                ->orderBy(
                    'harvest_start_at'
                )
                ->get()
                ->map(
                    fn (
                        ExpectedHarvest $harvest
                    ) => [
                        'id' =>
                            $harvest->id,

                        'producer_id' =>
                            $harvest
                                ->producer_id,

                        'commodity_id' =>
                            $harvest
                                ->commodity_id,

                        'producer_name' =>
                            $harvest
                                ->producer
                                ->name,

                        'commodity_name' =>
                            $harvest
                                ->commodity
                                ->name,

                        'expected_min_volume' =>
                            (string)
                            $harvest
                                ->expected_min_volume,

                        'expected_max_volume' =>
                            (string)
                            $harvest
                                ->expected_max_volume,

                        'harvest_start_at' =>
                            $harvest
                                ->harvest_start_at
                                ?->toIso8601String(),

                        'harvest_end_at' =>
                            $harvest
                                ->harvest_end_at
                                ?->toIso8601String(),

                        'unit_symbol' =>
                            $harvest
                                ->unit
                                ->symbol,
                    ]
                )
                ->values();

        return [
            'producers' =>
                $producers,

            'expectedHarvests' =>
                $expectedHarvests,
        ];
    }

    private function serializeForecast(
        SupplyCommitment $commitment
    ): array {
        return [
            'id' =>
                $commitment
                    ->forecast
                    ->id,

            'forecast_code' =>
                $commitment
                    ->forecast
                    ->forecast_code,

            'commodity' => [
                'id' =>
                    $commitment
                        ->forecast
                        ->commodity
                        ->id,

                'name' =>
                    $commitment
                        ->forecast
                        ->commodity
                        ->name,
            ],

            'unit' => [
                'id' =>
                    $commitment
                        ->forecast
                        ->unit
                        ->id,

                'name' =>
                    $commitment
                        ->forecast
                        ->unit
                        ->name,

                'symbol' =>
                    $commitment
                        ->forecast
                        ->unit
                        ->symbol,
            ],

            'required_start_at' =>
                $commitment
                    ->forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $commitment
                    ->forecast
                    ->required_end_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeVersion(
        CommitmentVersion $version
    ): array {
        return [
            'id' =>
                $version->id,

            'version_no' =>
                $version->version_no,

            'min_volume' =>
                (string)
                $version->min_volume,

            'max_volume' =>
                (string)
                $version->max_volume,

            'unit_id' =>
                $version->unit_id,

            'availability_start_at' =>
                $version
                    ->availability_start_at
                    ?->toIso8601String(),

            'availability_end_at' =>
                $version
                    ->availability_end_at
                    ?->toIso8601String(),

            'notes' =>
                $version->notes,

            'change_reason' =>
                $version
                    ->change_reason,

            'operator_justification' =>
                $version
                    ->operator_justification,
        ];
    }
}