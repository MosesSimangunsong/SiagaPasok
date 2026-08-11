<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoFallbackOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoFallbackOfferController extends Controller
{
    public function prepare(
        Request $request,
        DemoFallbackOfferService $service
    ): RedirectResponse {
        $service->prepareAndSubmit(
            $request->user()
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Fallback Offer simulasi 160 kg sudah disiapkan dan dikirim untuk approval Manager Mitra Lestari.'
            );
    }

    public function approve(
        Request $request,
        DemoFallbackOfferService $service
    ): RedirectResponse {
        $service->approveForAvailability(
            $request->user()
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Fallback Offer simulasi sekarang AVAILABLE dengan reserve 160 kg.'
            );
    }

    public function accept(
        Request $request,
        DemoFallbackOfferService $service
    ): RedirectResponse {
        $service->accept(
            $request->user()
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Fallback 150 kg diterima. Alokasi 150 kg, reserve 10 kg dilepas, dan Safe Supply kembali 400 kg.'
            );
    }
}