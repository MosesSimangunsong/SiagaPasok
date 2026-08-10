<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\CancelFallbackRequestRequest;
use App\Models\FallbackRequest;
use App\Services\Fallback\FallbackRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FallbackRequestActionController extends Controller
{
    public function __construct(
        private readonly FallbackRequestService $requestService
    ) {
    }

    public function submit(
        Request $request,
        FallbackRequest $fallbackRequest
    ): RedirectResponse {
        Gate::authorize(
            'submit',
            $fallbackRequest
        );

        $this->requestService
            ->submit(
                $request->user(),
                $fallbackRequest
            );

        return redirect()
            ->route(
                'kdkmp.fallback-requests.show',
                $fallbackRequest
            )
            ->with(
                'success',
                'Fallback Request berhasil dikirim untuk persetujuan Manager.'
            );
    }

    public function cancel(
        CancelFallbackRequestRequest $request,
        FallbackRequest $fallbackRequest
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->requestService
            ->cancel(
                $request->user(),
                $fallbackRequest,
                $validated[
                    'cancellation_reason'
                ]
            );

        return redirect()
            ->route(
                'kdkmp.fallback-requests.show',
                $fallbackRequest
            )
            ->with(
                'success',
                'Fallback Request berhasil dibatalkan.'
            );
    }
}