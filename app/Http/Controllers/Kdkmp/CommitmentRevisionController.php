<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreCommitmentRevisionRequest;
use App\Models\CommitmentVersion;
use App\Models\SupplyCommitment;
use App\Services\Commitment\CommitmentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentRevisionController extends Controller
{
    public function __construct(
        private readonly CommitmentWorkflowService $workflowService
    ) {
    }

    public function create(
        SupplyCommitment $commitment
    ): Response {
        Gate::authorize(
            'createRevision',
            $commitment
        );

        $commitment->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',
            'producer',
            'expectedHarvest',
            'activeVersion.unit',

            'versions' => fn ($query) =>
                $query
                    ->with('unit')
                    ->orderByDesc(
                        'version_no'
                    ),
        ]);

        $baseVersion =
            $commitment->activeVersion
            ?? $commitment
                ->versions
                ->first();

        abort_unless(
            $baseVersion
            instanceof CommitmentVersion,
            409,
            'Commitment belum memiliki version.'
        );

        return Inertia::render(
            'Kdkmp/Commitments/Revision',
            [
                'commitment' => [
                    'id' =>
                        $commitment->id,

                    'current_confidence' =>
                        $commitment
                            ->current_confidence
                            ?->value,

                    'forecast' => [
                        'id' =>
                            $commitment
                                ->forecast
                                ->id,

                        'forecast_code' =>
                            $commitment
                                ->forecast
                                ->forecast_code,

                        'commodity_name' =>
                            $commitment
                                ->forecast
                                ->commodity
                                ->name,

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
                    ],

                    'producer' => [
                        'id' =>
                            $commitment
                                ->producer
                                ->id,

                        'producer_code' =>
                            $commitment
                                ->producer
                                ->producer_code,

                        'name' =>
                            $commitment
                                ->producer
                                ->name,
                    ],

                    'expected_harvest' =>
                        $commitment
                            ->expectedHarvest
                            ? [
                                'id' =>
                                    $commitment
                                        ->expectedHarvest
                                        ->id,

                                'expected_min_volume' =>
                                    (string)
                                    $commitment
                                        ->expectedHarvest
                                        ->expected_min_volume,

                                'expected_max_volume' =>
                                    (string)
                                    $commitment
                                        ->expectedHarvest
                                        ->expected_max_volume,
                            ]
                            : null,
                ],

                'baseVersion' => [
                    'id' =>
                        $baseVersion->id,

                    'version_no' =>
                        $baseVersion
                            ->version_no,

                    'min_volume' =>
                        (string)
                        $baseVersion
                            ->min_volume,

                    'max_volume' =>
                        (string)
                        $baseVersion
                            ->max_volume,

                    'unit_id' =>
                        $baseVersion
                            ->unit_id,

                    'availability_start_at' =>
                        $baseVersion
                            ->availability_start_at
                            ?->toIso8601String(),

                    'availability_end_at' =>
                        $baseVersion
                            ->availability_end_at
                            ?->toIso8601String(),

                    'notes' =>
                        $baseVersion
                            ->notes,

                    'operator_justification' =>
                        $baseVersion
                            ->operator_justification,
                ],
            ]
        );
    }

    public function store(
        StoreCommitmentRevisionRequest $request,
        SupplyCommitment $commitment
    ): RedirectResponse {
        $this->workflowService->createRevision(
            $request->user(),
            $commitment,
            $request->validated()
        );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Draft revisi komitmen berhasil dibuat.'
            );
    }
}