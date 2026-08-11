<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoResetController extends Controller
{
    public function __invoke(
        Request $request,
        DemoResetService $demoResetService
    ): RedirectResponse {
        $forecast =
            $demoResetService->reset(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Scenario simulasi berhasil direset. %s kembali ke baseline Safe Supply 400 kg.',
                    $forecast->forecast_code,
                )
            );
    }
}