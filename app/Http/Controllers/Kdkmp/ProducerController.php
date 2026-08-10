<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreProducerRequest;
use App\Http\Requests\Kdkmp\UpdateProducerRequest;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Services\Supply\ProducerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProducerController extends Controller
{
    public function __construct(
        private readonly ProducerService $producerService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            Producer::class
        );

        $user = request()->user();

        $producers = Producer::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->withCount('expectedHarvests')
            ->with([
                'expectedHarvests' => fn ($query) =>
                    $query
                        ->where(
                            'harvest_end_at',
                            '>=',
                            now()
                        )
                        ->with([
                            'commodity',
                            'unit',
                        ])
                        ->orderBy(
                            'harvest_start_at'
                        )
                        ->orderBy('id'),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(
                fn (Producer $producer) =>
                    $this->serializeProducerListItem(
                        $producer
                    )
            )
            ->values();

        return Inertia::render(
            'Kdkmp/Producers/Index',
            [
                'producers' => $producers,

                'canCreate' => $user->can(
                    'create',
                    Producer::class
                ),
            ]
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Producer::class
        );

        return Inertia::render(
            'Kdkmp/Producers/Create'
        );
    }

    public function store(
        StoreProducerRequest $request
    ): RedirectResponse {
        $producer = $this->producerService->create(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route(
                'kdkmp.producers.show',
                $producer
            )
            ->with(
                'success',
                'Produsen berhasil ditambahkan.'
            );
    }

    public function show(
        Producer $producer
    ): Response {
        Gate::authorize(
            'view',
            $producer
        );

        $user = request()->user();

        $producer->loadCount(
            'expectedHarvests'
        );

        $producer->load([
            'createdBy',

            'expectedHarvests' => fn ($query) =>
                $query
                    ->with([
                        'commodity',
                        'unit',
                        'lastUpdatedBy',
                    ])
                    ->orderByDesc(
                        'harvest_start_at'
                    )
                    ->orderByDesc('id'),
        ]);

        return Inertia::render(
            'Kdkmp/Producers/Show',
            [
                'producer' =>
                    $this->serializeProducerDetail(
                        $producer
                    ),

                'can' => [
                    'edit' => $user->can(
                        'update',
                        $producer
                    ),

                    'setActiveState' => $user->can(
                        'setActiveState',
                        $producer
                    ),

                    'createExpectedHarvest' =>
                        $producer->is_active
                        && $user->can(
                            'create',
                            ExpectedHarvest::class
                        ),
                ],
            ]
        );
    }

    public function edit(
        Producer $producer
    ): Response {
        Gate::authorize(
            'update',
            $producer
        );

        return Inertia::render(
            'Kdkmp/Producers/Edit',
            [
                'producer' =>
                    $this->serializeProducer(
                        $producer
                    ),
            ]
        );
    }

    public function update(
        UpdateProducerRequest $request,
        Producer $producer
    ): RedirectResponse {
        $producer = $this->producerService->update(
            $request->user(),
            $producer,
            $request->validated()
        );

        return redirect()
            ->route(
                'kdkmp.producers.show',
                $producer
            )
            ->with(
                'success',
                'Data produsen berhasil diperbarui.'
            );
    }

    private function serializeProducer(
        Producer $producer
    ): array {
        return [
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

            'contact_phone' =>
                $producer->contact_phone,

            'notes' =>
                $producer->notes,

            'is_active' =>
                (bool) $producer->is_active,

            'expected_harvest_count' =>
                (int) (
                    $producer
                        ->expected_harvests_count
                    ?? 0
                ),

            'created_at' =>
                $producer->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $producer->updated_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeProducerListItem(
        Producer $producer
    ): array {
        $nearestHarvest =
            $producer->expectedHarvests->first();

        $commodities =
            $producer
                ->expectedHarvests
                ->pluck('commodity')
                ->filter()
                ->unique('id')
                ->values()
                ->map(
                    fn ($commodity) => [
                        'id' =>
                            $commodity->id,

                        'code' =>
                            $commodity->code,

                        'name' =>
                            $commodity->name,
                    ]
                );

        return [
            ...$this->serializeProducer(
                $producer
            ),

            'planning_commodities' =>
                $commodities,

            'nearest_expected_harvest' =>
                $nearestHarvest
                    ? [
                        'id' =>
                            $nearestHarvest->id,

                        'commodity_name' =>
                            $nearestHarvest
                                ->commodity
                                ->name,

                        'expected_min_volume' =>
                            (string)
                            $nearestHarvest
                                ->expected_min_volume,

                        'expected_max_volume' =>
                            (string)
                            $nearestHarvest
                                ->expected_max_volume,

                        'harvest_start_at' =>
                            $nearestHarvest
                                ->harvest_start_at
                                ?->toIso8601String(),

                        'harvest_end_at' =>
                            $nearestHarvest
                                ->harvest_end_at
                                ?->toIso8601String(),

                        'unit' => [
                            'symbol' =>
                                $nearestHarvest
                                    ->unit
                                    ->symbol,

                            'decimal_precision' =>
                                $nearestHarvest
                                    ->unit
                                    ->decimal_precision,
                        ],
                    ]
                    : null,
        ];
    }

    private function serializeProducerDetail(
        Producer $producer
    ): array {
        return [
            ...$this->serializeProducer(
                $producer
            ),

            'created_by' =>
                $producer->createdBy
                    ? [
                        'id' =>
                            $producer->createdBy->id,

                        'name' =>
                            $producer->createdBy->name,
                    ]
                    : null,

            'expected_harvests' =>
                $producer
                    ->expectedHarvests
                    ->map(
                        fn (
                            ExpectedHarvest $harvest
                        ) => [
                            'id' =>
                                $harvest->id,

                            'commodity' => [
                                'id' =>
                                    $harvest
                                        ->commodity
                                        ->id,

                                'code' =>
                                    $harvest
                                        ->commodity
                                        ->code,

                                'name' =>
                                    $harvest
                                        ->commodity
                                        ->name,
                            ],

                            'unit' => [
                                'id' =>
                                    $harvest
                                        ->unit
                                        ->id,

                                'name' =>
                                    $harvest
                                        ->unit
                                        ->name,

                                'symbol' =>
                                    $harvest
                                        ->unit
                                        ->symbol,

                                'decimal_precision' =>
                                    $harvest
                                        ->unit
                                        ->decimal_precision,
                            ],

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

                            'notes' =>
                                $harvest->notes,

                            'last_updated_by' =>
                                $harvest
                                    ->lastUpdatedBy
                                    ? [
                                        'id' =>
                                            $harvest
                                                ->lastUpdatedBy
                                                ->id,

                                        'name' =>
                                            $harvest
                                                ->lastUpdatedBy
                                                ->name,
                                    ]
                                    : null,

                            'updated_at' =>
                                $harvest
                                    ->updated_at
                                    ?->toIso8601String(),
                        ]
                    )
                    ->values(),
        ];
    }
}