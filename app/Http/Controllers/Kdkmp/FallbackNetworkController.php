<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\FallbackRequestStatus;
use App\Enums\NetworkRole;
use App\Http\Controllers\Controller;
use App\Models\FallbackRequest;
use App\Models\SupplyNetworkLink;
use App\Services\Fallback\FallbackRequestService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackNetworkController extends Controller
{
    public function __construct(
        private readonly FallbackRequestService $requestService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            FallbackRequest::class
        );

        $user =
            request()->user();

        /*
         * Satu organization dapat menjadi NETWORK
         * pada lebih dari satu SPPG.
         *
         * Jangan mengambil semua OPEN Request
         * secara global.
         */
        $sppgIds =
            SupplyNetworkLink::query()
                ->where(
                    'kdkmp_organization_id',
                    $user->organization_id
                )
                ->where(
                    'network_role',
                    NetworkRole::NETWORK->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->pluck(
                    'sppg_organization_id'
                );

        $requests =
            FallbackRequest::query()
                ->where(
                    'status',
                    FallbackRequestStatus::OPEN->value
                )
                ->whereHas(
                    'forecast',
                    fn ($query) =>
                        $query->whereIn(
                            'sppg_organization_id',
                            $sppgIds
                        )
                )
                /*
                 * Defensive C17:
                 *
                 * requester organization sendiri
                 * tidak tampil sebagai supplier
                 * terhadap Request miliknya.
                 */
                ->where(
                    'requester_organization_id',
                    '!=',
                    $user->organization_id
                )
                ->with([
                    'forecast.commodity',
                    'forecast.unit',
                    'requesterOrganization',
                    'unit',
                ])
                ->orderBy(
                    'response_deadline_at'
                )
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        FallbackRequest $fallbackRequest
                    ) =>
                        $this->serializeBroadcast(
                            $fallbackRequest
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/FallbackNetwork/Index',
            [
                'requests' =>
                    $requests,
            ]
        );
    }

    public function show(
        FallbackRequest $fallbackRequest
    ): Response {
        Gate::authorize(
            'viewBroadcast',
            $fallbackRequest
        );

        /*
         * Hanya relations yang memang termasuk
         * broadcast-safe payload.
         *
         * Jangan eager-load:
         * - offers.sources;
         * - supply commitments;
         * - producers;
         * - expected harvest;
         * - requester maker/reviewer actors.
         */
        $fallbackRequest->load([
            'forecast.commodity',
            'forecast.unit',
            'requesterOrganization',
            'unit',
        ]);

        return Inertia::render(
            'Kdkmp/FallbackNetwork/Show',
            [
                'request' =>
                    $this->serializeBroadcast(
                        $fallbackRequest
                    ),

                'can' => [
                    /*
                     * Operator NETWORK akan memakai
                     * flag ini untuk Create Offer
                     * pada M07.6A3.2.
                     */
                    'createOffer' =>
    Gate::allows(
        'createForRequest',
        [
            \App\Models\FallbackOffer::class,
            $fallbackRequest,
        ]
    ),
                ],
            ]
        );
    }

    private function serializeBroadcast(
        FallbackRequest $fallbackRequest
    ): array {
        return [
            'id' =>
                $fallbackRequest->id,

            /*
             * Requester identity memang termasuk
             * broadcast-safe contract.
             */
            'requester_organization' => [
                'id' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->id,

                'code' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->code,

                'name' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->name,

                'general_location' =>
                    $fallbackRequest
                        ->requesterOrganization
                        ->general_location,
            ],

            'commodity' => [
                'id' =>
                    $fallbackRequest
                        ->forecast
                        ->commodity
                        ->id,

                'code' =>
                    $fallbackRequest
                        ->forecast
                        ->commodity
                        ->code,

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

                'decimal_precision' =>
                    $fallbackRequest
                        ->unit
                        ->decimal_precision,
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

            'broadcast_note' =>
                $fallbackRequest
                    ->broadcast_note,

            'status' =>
                $fallbackRequest
                    ->status
                    ->value,

            'status_label' =>
                $fallbackRequest
                    ->status
                    ->label(),
        ];
    }
}