<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreFallbackOfferRequest;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\SupplyCommitment;
use App\Services\Fallback\FallbackCapacityService;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackOfferController extends Controller
{
    public function __construct(
        private readonly FallbackOfferService $offerService,
        private readonly FallbackRequestService $requestService,
        private readonly FallbackCapacityService $capacityService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            FallbackOffer::class
        );

        $user =
            request()->user();

        $offers =
            FallbackOffer::query()
                ->where(
                    'supplier_organization_id',
                    $user->organization_id
                )
                ->with([
                    'fallbackRequest.forecast.commodity',
                    'fallbackRequest.requesterOrganization',
                    'unit',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (FallbackOffer $offer) =>
                        $this->serializeListItem(
                            $offer
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/FallbackOffers/Index',
            [
                'offers' =>
                    $offers,
            ]
        );
    }

    public function create(
        FallbackRequest $fallbackRequest
    ): Response {
        Gate::authorize(
            'createForRequest',
            [
                FallbackOffer::class,
                $fallbackRequest,
            ]
        );

        $fallbackRequest->load([
            'forecast.commodity',
            'forecast.unit',
            'requesterOrganization',
            'unit',
        ]);

        return Inertia::render(
            'Kdkmp/FallbackOffers/Create',
            [
                'request' =>
                    $this->serializeRequestContext(
                        $fallbackRequest
                    ),

                'sourceCommitments' =>
                    $this->sourceOptions(
                        request()
                            ->user()
                            ->organization_id,
                        $fallbackRequest
                    ),
            ]
        );
    }

    public function store(
        StoreFallbackOfferRequest $request,
        FallbackRequest $fallbackRequest
    ): RedirectResponse {
        $offer =
            $this->offerService
                ->createDraft(
                    $request->user(),
                    $fallbackRequest,
                    $request->validated()
                );

        return redirect()
            ->route(
                'kdkmp.fallback-offers.show',
                $offer
            )
            ->with(
                'success',
                'Draft Fallback Offer berhasil dibuat.'
            );
    }

    public function show(
        FallbackOffer $fallbackOffer
    ): Response {
        Gate::authorize(
            'viewSupplier',
            $fallbackOffer
        );

        $user =
            request()->user();

        $fallbackOffer->load([
            'fallbackRequest.forecast.commodity',
            'fallbackRequest.forecast.unit',
            'fallbackRequest.requesterOrganization',

            'supplierOrganization',
            'unit',

            'createdBy',
            'submittedBy',
            'supplierReviewedBy',
            'requesterDecidedBy',
            'withdrawnBy',

            'sources.supplyCommitment.producer',
            'sources.supplyCommitment.activeVersion.unit',
        ]);

        return Inertia::render(
            'Kdkmp/FallbackOffers/Show',
            [
                'offer' =>
                    $this->serializeSupplierDetail(
                        $fallbackOffer
                    ),

                'can' => [
                    'submit' =>
                        $fallbackOffer->isDraft()
                        && $user->can(
                            'submit',
                            $fallbackOffer
                        ),

                    'approve' =>
                        $fallbackOffer
                            ->isPendingApproval()
                        && $user->can(
                            'supplierReview',
                            $fallbackOffer
                        ),

                    'reject' =>
                        $fallbackOffer
                            ->isPendingApproval()
                        && $user->can(
                            'supplierReview',
                            $fallbackOffer
                        ),

                    'withdraw' =>
                        (
                            $fallbackOffer->isDraft()
                            || $fallbackOffer
                                ->isAvailable()
                        )
                        && $user->can(
                            'withdraw',
                            $fallbackOffer
                        ),
                ],
            ]
        );
    }

    private function sourceOptions(
        int $organizationId,
        FallbackRequest $fallbackRequest
    ) {
        $forecast =
            $fallbackRequest->forecast;

        return SupplyCommitment::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->with([
                'producer',
                'activeVersion.unit',
            ])
            ->orderBy('id')
            ->get()
            ->map(
                function (
                    SupplyCommitment $commitment
                ) use (
                    $forecast,
                    $organizationId,
                ): ?array {
                    $available =
                        $this->capacityService
                            ->availableCapacity(
                                $commitment,
                                $forecast,
                                $organizationId,
                                CarbonImmutable::now()
                            );

                    if (
                        FixedScaleDecimal::from(
                            $available
                        )->isZero()
                    ) {
                        return null;
                    }

                    return [
                        'id' =>
                            $commitment->id,

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

                        'current_confidence' =>
                            $commitment
                                ->current_confidence
                                ?->value,

                        'available_capacity' =>
                            $available,

                        'active_version' =>
                            $commitment
                                ->activeVersion
                                ? [
                                    'min_volume' =>
                                        (string)
                                        $commitment
                                            ->activeVersion
                                            ->min_volume,

                                    'max_volume' =>
                                        (string)
                                        $commitment
                                            ->activeVersion
                                            ->max_volume,

                                    'availability_start_at' =>
                                        $commitment
                                            ->activeVersion
                                            ->availability_start_at
                                            ?->toIso8601String(),

                                    'availability_end_at' =>
                                        $commitment
                                            ->activeVersion
                                            ->availability_end_at
                                            ?->toIso8601String(),

                                    'unit' => [
                                        'symbol' =>
                                            $commitment
                                                ->activeVersion
                                                ->unit
                                                ->symbol,
                                    ],
                                ]
                                : null,
                    ];
                }
            )
            ->filter()
            ->values();
    }

    private function serializeListItem(
        FallbackOffer $offer
    ): array {
        return [
            'id' =>
                $offer->id,

            'request' => [
                'id' =>
                    $offer
                        ->fallbackRequest
                        ->id,

                'requester_organization_name' =>
                    $offer
                        ->fallbackRequest
                        ->requesterOrganization
                        ->name,

                'commodity_name' =>
                    $offer
                        ->fallbackRequest
                        ->forecast
                        ->commodity
                        ->name,
            ],

            'offered_volume' =>
                (string)
                $offer->offered_volume,

            'accepted_volume' =>
                (string)
                $offer->accepted_volume,

            'unit' => [
                'symbol' =>
                    $offer
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $offer
                        ->unit
                        ->decimal_precision,
            ],

            'expires_at' =>
                $offer
                    ->expires_at
                    ?->toIso8601String(),

            'status' =>
                $offer
                    ->status
                    ->value,

            'status_label' =>
                $offer
                    ->status
                    ->label(),

            'created_at' =>
                $offer
                    ->created_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeRequestContext(
        FallbackRequest $fallbackRequest
    ): array {
        return [
            'id' =>
                $fallbackRequest->id,

            'requester_organization' => [
                'id' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->id,

                'name' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->name,
            ],

            'commodity' => [
                'id' =>
                    $fallbackRequest
                        ->forecast
                        ->commodity
                        ->id,

                'name' =>
                    $fallbackRequest
                        ->forecast
                        ->commodity
                        ->name,
            ],

            'requested_volume' =>
                (string)
                $fallbackRequest
                    ->requested_volume,

            'accepted_volume' =>
                $this->requestService
                    ->calculateAcceptedVolume(
                        $fallbackRequest
                    ),

            'remaining_volume' =>
                $this->requestService
                    ->calculateRemainingVolume(
                        $fallbackRequest
                    ),

            'unit' => [
                'id' =>
                    $fallbackRequest
                        ->unit
                        ->id,

                'name' =>
                    $fallbackRequest
                        ->unit
                        ->name,

                'symbol' =>
                    $fallbackRequest
                        ->unit
                        ->symbol,
            ],

            'required_start_at' =>
                $fallbackRequest
                    ->forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $fallbackRequest
                    ->forecast
                    ->required_end_at
                    ?->toIso8601String(),

            'response_deadline_at' =>
                $fallbackRequest
                    ->response_deadline_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeSupplierDetail(
        FallbackOffer $offer
    ): array {
        $forecast =
            $offer
                ->fallbackRequest
                ->forecast;

        return [
            ...$this->serializeListItem(
                $offer
            ),

            'availability_note' =>
                $offer
                    ->availability_note,

            'request' =>
                $this->serializeRequestContext(
                    $offer
                        ->fallbackRequest
                ),

            /*
             * SUPPLIER-PRIVATE.
             *
             * Jangan reuse serializer ini pada
             * requester incoming Offer controller.
             */
            'sources' =>
                $offer
                    ->sources
                    ->map(
                        function ($source) use (
                            $offer,
                            $forecast,
                        ): array {
                            $commitment =
                                $source
                                    ->supplyCommitment;

                            return [
                                'supply_commitment_id' =>
                                    $commitment->id,

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

                                'current_confidence' =>
                                    $commitment
                                        ->current_confidence
                                        ?->value,

                                'active_version' =>
                                    $commitment
                                        ->activeVersion
                                        ? [
                                            'min_volume' =>
                                                (string)
                                                $commitment
                                                    ->activeVersion
                                                    ->min_volume,

                                            'max_volume' =>
                                                (string)
                                                $commitment
                                                    ->activeVersion
                                                    ->max_volume,

                                            'availability_start_at' =>
                                                $commitment
                                                    ->activeVersion
                                                    ->availability_start_at
                                                    ?->toIso8601String(),

                                            'availability_end_at' =>
                                                $commitment
                                                    ->activeVersion
                                                    ->availability_end_at
                                                    ?->toIso8601String(),
                                        ]
                                        : null,

                                'current_available_capacity' =>
                                    $this
                                        ->capacityService
                                        ->availableCapacity(
                                            $commitment,
                                            $forecast,
                                            $offer
                                                ->supplier_organization_id,
                                            CarbonImmutable::now()
                                        ),

                                'ledger' => [
                                    'reserved_volume' =>
                                        (string)
                                        $source
                                            ->reserved_volume,

                                    'allocated_volume' =>
                                        (string)
                                        $source
                                            ->allocated_volume,

                                    'released_volume' =>
                                        (string)
                                        $source
                                            ->released_volume,

                                    'reserved_at' =>
                                        $source
                                            ->reserved_at
                                            ?->toIso8601String(),

                                    'allocated_at' =>
                                        $source
                                            ->allocated_at
                                            ?->toIso8601String(),

                                    'released_at' =>
                                        $source
                                            ->released_at
                                            ?->toIso8601String(),
                                ],
                            ];
                        }
                    )
                    ->values(),

            'workflow' => [
                'created_by' =>
                    $offer
                        ->createdBy
                        ?->name,

                'submitted_by' =>
                    $offer
                        ->submittedBy
                        ?->name,

                'submitted_at' =>
                    $offer
                        ->submitted_at
                        ?->toIso8601String(),

                'supplier_reviewed_by' =>
                    $offer
                        ->supplierReviewedBy
                        ?->name,

                'supplier_reviewed_at' =>
                    $offer
                        ->supplier_reviewed_at
                        ?->toIso8601String(),

                'supplier_review_reason' =>
                    $offer
                        ->supplier_review_reason,

                'requester_decided_at' =>
                    $offer
                        ->requester_decided_at
                        ?->toIso8601String(),

                'requester_decision_reason' =>
                    $offer
                        ->requester_decision_reason,

                'withdrawn_at' =>
                    $offer
                        ->withdrawn_at
                        ?->toIso8601String(),

                'withdrawal_reason' =>
                    $offer
                        ->withdrawal_reason,
            ],
        ];
    }
}