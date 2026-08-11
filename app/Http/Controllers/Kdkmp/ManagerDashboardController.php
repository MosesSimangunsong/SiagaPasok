<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ManagerDashboardProjectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ManagerDashboardController extends Controller
{
    public function __construct(
        private readonly ManagerDashboardProjectionService
            $projectionService,
    ) {
    }

    public function __invoke(
        Request $request
    ): Response {
        return Inertia::render(
            'Kdkmp/Manager/Dashboard',
            $this->projectionService
                ->build(
                    $request->user()
                )
        );
    }
}