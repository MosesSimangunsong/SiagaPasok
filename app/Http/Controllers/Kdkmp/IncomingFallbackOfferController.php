<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\FallbackOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\AcceptFallbackOfferRequest;
use App\Http\Requests\Kdkmp\RejectIncomingFallbackOfferRequest;
use App\Models\FallbackOffer;
use App\Models\User;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IncomingFallbackOfferController extends Controller
{
    public function __construct(
        private readonly FallbackOfferService $offerService,
        private readonly FallbackRequestService $requestService,
    ) {
    }

    public function index(): Response
    {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        $offers =
            FallbackOffer::query()
                ->where(
                    'status',
                    FallbackOfferStatus
                        ::AVAILABLE
                        ->value
                )
                ->whereHas(
                    'fallbackRequest',
                    fn ($query) =>
                        $query->where(
                            'requester_organization_id',
                            $user->organization_id
                        )
                )
                ->with([
                    'fallbackRequest.forecast.commodity',
                    'fallbackRequest.unit',
                    'supplierOrganization',
                    'unit',
                ])
                ->orderBy(
                    'expires_at'
                )
                ->orderBy('id')
                ->get()
                ->map(
                    fn (FallbackOffer $offer) =>
                        $this->serializeRequesterOffer(
                            $offer
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/IncomingOffers/Index',
            [
                'offers' =>
                    $offers,
            ]
        );
    }

    public function show(
        FallbackOffer $fallbackOffer
    ): Response {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        Gate::authorize(
            'viewRequester',
            $fallbackOffer
        );

        /*
         * HARD PRIVACY BOUNDARY:
         *
         * Tidak eager-load sources,
         * Commitment, Producer atau ledger.
         */
        $fallbackOffer->load([
            'fallbackRequest.forecast.commodity',
            'fallbackRequest.forecast.unit',
            'fallbackRequest.unit',
            'supplierOrganization',
            'unit',
        ]);

        return Inertia::render(
            'Kdkmp/Manager/IncomingOffers/Show',
            [
                'offer' =>
                    $this->serializeRequesterOffer(
                        $fallbackOffer
                    ),

                'can' => [
                    'accept' =>
                        $fallbackOffer
                            ->isAvailable()
                        && $user->can(
                            'requesterDecision',
                            $fallbackOffer
                        ),

                    'reject' =>
                        $fallbackOffer
                            ->isAvailable()
                        && $user->can(
                            'requesterDecision',
                            $fallbackOffer
                        ),
                ],
            ]
        );
    }

    public function accept(
        AcceptFallbackOfferRequest $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->offerService
            ->accept(
                $request->user(),
                $fallbackOffer,
                (string)
                $validated[
                    'accepted_volume'
                ]
            );

        return redirect()
            ->route(
                'kdkmp.manager.incoming-offers.index'
            )
            ->with(
                'success',
                'Fallback Offer berhasil diterima.'
            );
    }

    public function reject(
        RejectIncomingFallbackOfferRequest $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->offerService
            ->rejectByRequesterManager(
                $request->user(),
                $fallbackOffer,
                $validated[
                    'requester_decision_reason'
                ] ?? null
            );

        return redirect()
            ->route(
                'kdkmp.manager.incoming-offers.index'
            )
            ->with(
                'success',
                'Fallback Offer berhasil ditolak.'
            );
    }

    private function serializeRequesterOffer(
        FallbackOffer $offer
    ): array {
        return [
            'id' =>
                $offer->id,

            'supplier_organization' => [
                'id' =>
                    $offer
                        ->supplierOrganization
                        ->id,

                'code' =>
                    $offer
                        ->supplierOrganization
                        ->code,

                'name' =>
                    $offer
                        ->supplierOrganization
                        ->name,

                'general_location' =>
                    $offer
                        ->supplierOrganization
                        ->general_location,
            ],

            'offered_volume' =>
                (string)
                $offer
                    ->offered_volume,

            'accepted_volume' =>
                (string)
                $offer
                    ->accepted_volume,

            'unit' => [
                'id' =>
                    $offer
                        ->unit
                        ->id,

                'name' =>
                    $offer
                        ->unit
                        ->name,

                'symbol' =>
                    $offer
                        ->unit
                        ->symbol,
            ],

            'availability_note' =>
                $offer
                    ->availability_note,

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

            'request' => [
                'id' =>
                    $offer
                        ->fallbackRequest
                        ->id,

                'commodity' => [
                    'id' =>
                        $offer
                            ->fallbackRequest
                            ->forecast
                            ->commodity
                            ->id,

                    'name' =>
                        $offer
                            ->fallbackRequest
                            ->forecast
                            ->commodity
                            ->name,
                ],

                'requested_volume' =>
                    (string)
                    $offer
                        ->fallbackRequest
                        ->requested_volume,

                'accepted_volume' =>
                    $this->requestService
                        ->calculateAcceptedVolume(
                            $offer
                                ->fallbackRequest
                        ),

                'remaining_volume' =>
                    $this->requestService
                        ->calculateRemainingVolume(
                            $offer
                                ->fallbackRequest
                        ),

                'required_start_at' =>
                    $offer
                        ->fallbackRequest
                        ->forecast
                        ->required_start_at
                        ?->toIso8601String(),

                'required_end_at' =>
                    $offer
                        ->fallbackRequest
                        ->forecast
                        ->required_end_at
                        ?->toIso8601String(),
            ],
        ];
    }

    private function assertManager(
        User $user
    ): void {
        if (
            ! $user->isKdkmpManager()
            || ! $user
                ->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang dapat mengakses Incoming Fallback Offers.'
            );
        }
    }
}