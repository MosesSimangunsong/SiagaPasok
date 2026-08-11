<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoFallbackSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoFallbackSourceController extends Controller
{
    public function prepare(
        Request $request,
        DemoFallbackSourceService $service
    ): RedirectResponse {
        $commitment =
            $service->prepareAndSubmit(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Source fallback simulasi 160 kg sudah disiapkan dan dikirim untuk approval Manager. Commitment #%d.',
                    $commitment->id,
                )
            );
    }

    public function approve(
        Request $request,
        DemoFallbackSourceService $service
    ): RedirectResponse {
        $commitment =
            $service->approve(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Source fallback simulasi 160 kg sudah APPROVED dan GREEN. Commitment #%d siap menjadi source Offer.',
                    $commitment->id,
                )
            );
    }
}