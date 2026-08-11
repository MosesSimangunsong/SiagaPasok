<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoFallbackRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoFallbackRequestController extends Controller
{
    public function prepare(
        Request $request,
        DemoFallbackRequestService $service
    ): RedirectResponse {
        $fallbackRequest =
            $service->prepareAndSubmit(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Fallback Request simulasi %s kg sudah disiapkan dan dikirim untuk approval Manager.',
                    $fallbackRequest->requested_volume,
                )
            );
    }

    public function broadcast(
        Request $request,
        DemoFallbackRequestService $service
    ): RedirectResponse {
        $fallbackRequest =
            $service->approveBroadcast(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Fallback Request simulasi %s kg sudah disetujui dan dibroadcast ke NETWORK.',
                    $fallbackRequest->requested_volume,
                )
            );
    }
}