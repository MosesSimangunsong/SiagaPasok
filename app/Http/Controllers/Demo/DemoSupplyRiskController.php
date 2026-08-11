<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoSupplyRiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoSupplyRiskController extends Controller
{
    public function __invoke(
        Request $request,
        DemoSupplyRiskService $demoSupplyRiskService
    ): RedirectResponse {
        $metrics =
            $demoSupplyRiskService->apply(
                $request->user()
            );

        return redirect()
            ->route('home')
            ->with(
                'success',
                sprintf(
                    'Gangguan pasokan simulasi diterapkan. Safe Supply %s kg, At-Risk %s kg, Shortfall %s kg.',
                    $metrics->directSafeSupply,
                    $metrics->atRiskSupply,
                    $metrics->shortfall,
                )
            );
    }
}