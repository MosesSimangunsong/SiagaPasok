<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Services\Dashboard\OperatorDashboardProjectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OperatorDashboardController extends Controller
{
    public function __construct(
        private readonly OperatorDashboardProjectionService
            $projectionService,
    ) {
    }

    public function __invoke(
        Request $request
    ): Response {
        Gate::authorize(
            'viewKdkmpIndex',
            DemandForecast::class
        );

        return Inertia::render(
            'Kdkmp/Operator/Dashboard',
            $this->projectionService
                ->build(
                    $request->user()
                )
        );
    }
}