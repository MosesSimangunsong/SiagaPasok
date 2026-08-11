<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Services\Dashboard\SppgDashboardProjectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SppgDashboardController extends Controller
{
    public function __construct(
        private readonly SppgDashboardProjectionService
            $projectionService,
    ) {
    }

    public function __invoke(
        Request $request
    ): Response {
        Gate::authorize(
            'viewAny',
            DemandForecast::class
        );

        return Inertia::render(
            'Sppg/Dashboard',
            $this->projectionService
                ->build(
                    $request->user()
                )
        );
    }
}