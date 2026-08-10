<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Models\FallbackOffer;
use App\Services\Fallback\FallbackOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FallbackOfferActionController extends Controller
{
    public function __construct(
        private readonly FallbackOfferService $offerService
    ) {
    }

    public function submit(
        Request $request,
        FallbackOffer $fallbackOffer
    ): RedirectResponse {
        Gate::authorize(
            'submit',
            $fallbackOffer
        );

        $this->offerService
            ->submit(
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
                'Fallback Offer berhasil dikirim untuk review Manager.'
            );
    }
}