<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sppg\StoreFulfilmentFeedbackRequest;
use App\Models\DemandForecast;
use App\Models\FulfilmentFeedback;
use App\Services\Fulfilment\FulfilmentFeedbackQueryService;
use App\Services\Fulfilment\FulfilmentFeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FulfilmentFeedbackController extends Controller
{
    public function index(
        Request $request,
        FulfilmentFeedbackQueryService $queryService,
    ): Response {
        Gate::authorize(
            'viewAny',
            FulfilmentFeedback::class
        );

        return Inertia::render(
            'Sppg/Fulfilment/Index',
            [
                'forecasts' =>
                    $queryService
                        ->sppgIndex(
                            $request->user()
                        ),
            ]
        );
    }

    public function show(
        Request $request,
        DemandForecast $forecast,
        FulfilmentFeedbackQueryService $queryService,
    ): Response {
        Gate::authorize(
            'view',
            $forecast
        );

        abort_unless(
            $forecast->isClosed(),
            404
        );

        return Inertia::render(
            'Sppg/Fulfilment/Show',
            $queryService
                ->sppgForecast(
                    $request->user(),
                    $forecast
                )
        );
    }

    public function store(
        StoreFulfilmentFeedbackRequest $request,
        DemandForecast $forecast,
        int $contributorOrganizationId,
        FulfilmentFeedbackService $service,
    ): RedirectResponse {
        Gate::authorize(
            'create',
            FulfilmentFeedback::class
        );

        /*
         * Ownership tetap diperiksa juga di
         * command service. Gate di sini adalah
         * HTTP boundary defense.
         */
        Gate::authorize(
            'view',
            $forecast
        );

        $service->record(
            $request->user(),
            $forecast,
            $contributorOrganizationId,
            $request->validated()
        );

        return redirect()
            ->route(
                'sppg.fulfilments.show',
                $forecast
            )
            ->with(
                'success',
                'Umpan Balik Pemenuhan berhasil dicatat.'
            );
    }
}