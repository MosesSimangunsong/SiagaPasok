<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Models\FulfilmentFeedback;
use App\Services\Fulfilment\FulfilmentFeedbackQueryService;
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
        abort_unless(
            $request
                ->user()
                ->belongsToKdkmp(),
            403
        );

        Gate::authorize(
            'viewAny',
            FulfilmentFeedback::class
        );

        return Inertia::render(
            'Kdkmp/Fulfilment/Index',
            [
                'feedbacks' =>
                    $queryService
                        ->kdkmpIndex(
                            $request->user()
                        ),
            ]
        );
    }

    public function show(
        Request $request,
        FulfilmentFeedback $fulfilmentFeedback,
        FulfilmentFeedbackQueryService $queryService,
    ): Response {
        abort_unless(
            $request
                ->user()
                ->belongsToKdkmp(),
            403
        );

        Gate::authorize(
            'view',
            $fulfilmentFeedback
        );

        return Inertia::render(
            'Kdkmp/Fulfilment/Show',
            [
                'feedback' =>
                    $queryService
                        ->kdkmpFeedback(
                            $request->user(),
                            $fulfilmentFeedback
                        ),
            ]
        );
    }
}