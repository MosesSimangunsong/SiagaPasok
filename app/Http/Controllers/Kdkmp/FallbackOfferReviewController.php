<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\FallbackOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\RejectOutgoingFallbackOfferRequest;
use App\Http\Requests\Kdkmp\WithdrawFallbackOfferRequest;
use App\Models\FallbackOffer;
use App\Models\User;
use App\Services\Fallback\FallbackOfferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackOfferReviewController extends Controller
{
    public function __construct(
        private readonly FallbackOfferService $offerService
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
                    'supplier_organization_id',
                    $user->organization_id
                )
                ->where(
                    'status',
                    FallbackOfferStatus
                        ::PENDING_APPROVAL
                        ->value
                )
                ->with([
                    'fallbackRequest.forecast.commodity',
                    'fallbackRequest.requesterOrganization',
                    'unit',
                    'submittedBy',
                ])
                ->orderBy(
                    'submitted_at'
                )
                ->orderBy('id')
                ->get()
                ->map(
                    fn (FallbackOffer $offer) => [
                        'id' =>
                            $offer->id,

                        'request_id' =>
                            $offer
                                ->fallback_request_id,

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

                        'offered_volume' =>
                            (string)
                            $offer
                                ->offered_volume,

                        'unit_symbol' =>
                            $offer
                                ->unit
                                ->symbol,

                        'expires_at' =>
                            $offer
                                ->expires_at
                                ?->toIso8601String(),

                        'submitted_by' =>
                            $offer
                                ->submittedBy
                                ?->name,

                        'submitted_at' =>
                            $offer
                                ->submitted_at
                                ?->toIso8601String(),
                    ]
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/OutgoingOffers/Index',
            [
                'offers' =>
                    $offers,
            ]
        );
    }

    public function approve(
        Request $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        Gate::authorize(
            'supplierReview',
            $fallbackOffer
        );

        $this->offerService
            ->approveForAvailability(
                $request->user(),
                $fallbackOffer
            );

        return redirect()
            ->route(
                'kdkmp.fallback-offers.show',
                $fallbackOffer
            )
            ->with(
                'success',
                'Fallback Offer disetujui dan capacity berhasil di-reserve.'
            );
    }

    public function reject(
        RejectOutgoingFallbackOfferRequest $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->offerService
            ->rejectBySupplierManager(
                $request->user(),
                $fallbackOffer,
                $validated[
                    'supplier_review_reason'
                ] ?? null
            );

        return redirect()
            ->route(
                'kdkmp.fallback-offers.show',
                $fallbackOffer
            )
            ->with(
                'success',
                'Fallback Offer ditolak oleh Manager supplier.'
            );
    }

    public function withdraw(
        WithdrawFallbackOfferRequest $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->offerService
            ->withdraw(
                $request->user(),
                $fallbackOffer,
                $validated[
                    'withdrawal_reason'
                ] ?? null
            );

        return redirect()
            ->route(
                'kdkmp.fallback-offers.show',
                $fallbackOffer
            )
            ->with(
                'success',
                'Fallback Offer berhasil ditarik.'
            );
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
                'Hanya KDKMP Manager aktif yang dapat mengakses Outgoing Offer Review.'
            );
        }
    }
}