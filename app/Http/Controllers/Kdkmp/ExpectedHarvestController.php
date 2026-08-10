<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreExpectedHarvestRequest;
use App\Http\Requests\Kdkmp\UpdateExpectedHarvestRequest;
use App\Models\Commodity;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\Unit;
use App\Services\Supply\ExpectedHarvestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpectedHarvestController extends Controller
{
    public function __construct(
        private readonly ExpectedHarvestService $expectedHarvestService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            ExpectedHarvest::class
        );

        $user = request()->user();

        $expectedHarvests =
            ExpectedHarvest::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->with([
                    'producer',
                    'commodity',
                    'unit',
                    'lastUpdatedBy',
                ])
                ->orderBy('harvest_start_at')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        ExpectedHarvest $expectedHarvest
                    ) =>
                        $this->serializeExpectedHarvest(
                            $expectedHarvest
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/ExpectedHarvests/Index',
            [
                'expectedHarvests' =>
                    $expectedHarvests,

                'canCreate' =>
                    $user->can(
                        'create',
                        ExpectedHarvest::class
                    ),
            ]
        );
    }

    public function create(): Response
{
    Gate::authorize(
        'create',
        ExpectedHarvest::class
    );

    $user = request()->user();

    $selectedProducerId =
        request()->integer('producer_id');

    if ($selectedProducerId) {
        $isValidProducer =
            Producer::query()
                ->whereKey(
                    $selectedProducerId
                )
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists();

        if (! $isValidProducer) {
            $selectedProducerId = null;
        }
    }

    return Inertia::render(
        'Kdkmp/ExpectedHarvests/Create',
        [
            ...$this->formOptions(),

            'selectedProducerId' =>
                $selectedProducerId,
        ]
    );
}

    public function store(
        StoreExpectedHarvestRequest $request
    ): RedirectResponse {
        $expectedHarvest =
            $this->expectedHarvestService->create(
                $request->user(),
                $request->validated()
            );

        return redirect()
            ->route(
                'kdkmp.expected-harvests.show',
                $expectedHarvest
            )
            ->with(
                'success',
                'Ekspektasi panen berhasil ditambahkan.'
            );
    }

    public function show(
        ExpectedHarvest $expectedHarvest
    ): Response {
        Gate::authorize(
            'view',
            $expectedHarvest
        );

        $user = request()->user();

        $expectedHarvest->load([
            'producer',
            'commodity',
            'unit',
            'lastUpdatedBy',
        ]);

        return Inertia::render(
            'Kdkmp/ExpectedHarvests/Show',
            [
                'expectedHarvest' =>
                    $this->serializeExpectedHarvest(
                        $expectedHarvest
                    ),

                'can' => [
                    'edit' =>
                        $user->can(
                            'update',
                            $expectedHarvest
                        ),
                ],
            ]
        );
    }

    public function edit(
        ExpectedHarvest $expectedHarvest
    ): Response {
        Gate::authorize(
            'update',
            $expectedHarvest
        );

        $expectedHarvest->load([
            'producer',
            'commodity',
            'unit',
            'lastUpdatedBy',
        ]);

        return Inertia::render(
            'Kdkmp/ExpectedHarvests/Edit',
            [
                'expectedHarvest' =>
                    $this->serializeExpectedHarvest(
                        $expectedHarvest
                    ),

                ...$this->formOptions(),
            ]
        );
    }

    public function update(
        UpdateExpectedHarvestRequest $request,
        ExpectedHarvest $expectedHarvest
    ): RedirectResponse {
        $expectedHarvest =
            $this->expectedHarvestService->update(
                $request->user(),
                $expectedHarvest,
                $request->validated()
            );

        return redirect()
            ->route(
                'kdkmp.expected-harvests.show',
                $expectedHarvest
            )
            ->with(
                'success',
                'Ekspektasi panen berhasil diperbarui.'
            );
    }

    private function formOptions(): array
    {
        $user = request()->user();

        $producers = Producer::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'producer_code',
                'name',
                'village',
                'district',
            ])
            ->map(
                fn (Producer $producer) => [
                    'id' =>
                        $producer->id,

                    'producer_code' =>
                        $producer->producer_code,

                    'name' =>
                        $producer->name,

                    'village' =>
                        $producer->village,

                    'district' =>
                        $producer->district,
                ]
            )
            ->values();

        $commodities = Commodity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'default_unit_id',
            ])
            ->map(
                fn (Commodity $commodity) => [
                    'id' =>
                        $commodity->id,

                    'code' =>
                        $commodity->code,

                    'name' =>
                        $commodity->name,

                    'default_unit_id' =>
                        $commodity->default_unit_id,
                ]
            )
            ->values();

        $units = Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'symbol',
                'decimal_precision',
            ])
            ->map(
                fn (Unit $unit) => [
                    'id' =>
                        $unit->id,

                    'code' =>
                        $unit->code,

                    'name' =>
                        $unit->name,

                    'symbol' =>
                        $unit->symbol,

                    'decimal_precision' =>
                        $unit->decimal_precision,
                ]
            )
            ->values();

        return [
            'producers' =>
                $producers,

            'commodities' =>
                $commodities,

            'units' =>
                $units,
        ];
    }

    private function serializeExpectedHarvest(
        ExpectedHarvest $expectedHarvest
    ): array {
        return [
            'id' =>
                $expectedHarvest->id,

            'producer' => [
                'id' =>
                    $expectedHarvest->producer->id,

                'producer_code' =>
                    $expectedHarvest
                        ->producer
                        ->producer_code,

                'name' =>
                    $expectedHarvest->producer->name,

                'village' =>
                    $expectedHarvest->producer->village,

                'district' =>
                    $expectedHarvest->producer->district,

                'is_active' =>
                    (bool) $expectedHarvest
                        ->producer
                        ->is_active,
            ],

            'commodity' => [
                'id' =>
                    $expectedHarvest->commodity->id,

                'code' =>
                    $expectedHarvest->commodity->code,

                'name' =>
                    $expectedHarvest->commodity->name,

                'is_active' =>
                    (bool) $expectedHarvest
                        ->commodity
                        ->is_active,
            ],

            'unit' => [
                'id' =>
                    $expectedHarvest->unit->id,

                'code' =>
                    $expectedHarvest->unit->code,

                'name' =>
                    $expectedHarvest->unit->name,

                'symbol' =>
                    $expectedHarvest->unit->symbol,

                'decimal_precision' =>
                    $expectedHarvest
                        ->unit
                        ->decimal_precision,

                'is_active' =>
                    (bool) $expectedHarvest
                        ->unit
                        ->is_active,
            ],

            'expected_min_volume' =>
                (string) $expectedHarvest
                    ->expected_min_volume,

            'expected_max_volume' =>
                (string) $expectedHarvest
                    ->expected_max_volume,

            'harvest_start_at' =>
                $expectedHarvest
                    ->harvest_start_at
                    ?->toIso8601String(),

            'harvest_end_at' =>
                $expectedHarvest
                    ->harvest_end_at
                    ?->toIso8601String(),

            'notes' =>
                $expectedHarvest->notes,

            'last_updated_by' =>
                $expectedHarvest->lastUpdatedBy
                    ? [
                        'id' =>
                            $expectedHarvest
                                ->lastUpdatedBy
                                ->id,

                        'name' =>
                            $expectedHarvest
                                ->lastUpdatedBy
                                ->name,
                    ]
                    : null,

            'created_at' =>
                $expectedHarvest
                    ->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $expectedHarvest
                    ->updated_at
                    ?->toIso8601String(),
        ];
    }
}