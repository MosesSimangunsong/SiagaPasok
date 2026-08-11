<?php

namespace App\Http\Controllers\Demo;

use App\Enums\ReadinessType;
use App\Http\Controllers\Controller;
use App\Services\Demo\DemoContributorReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoContributorReadinessController extends Controller
{
    public function prepareLogistics(
        Request $request,
        DemoContributorReadinessService $service
    ): RedirectResponse {
        $service->prepareAndSubmit(
            $request->user(),
            ReadinessType::LOGISTICS
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Logistics Readiness simulasi sudah disiapkan dan dikirim untuk approval Manager.'
            );
    }

    public function approveLogistics(
        Request $request,
        DemoContributorReadinessService $service
    ): RedirectResponse {
        $service->approve(
            $request->user(),
            ReadinessType::LOGISTICS
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Logistics Readiness simulasi sudah APPROVED.'
            );
    }

    public function prepareDocument(
        Request $request,
        DemoContributorReadinessService $service
    ): RedirectResponse {
        $service->prepareAndSubmit(
            $request->user(),
            ReadinessType::DOCUMENT
        );

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Document Readiness simulasi sudah disiapkan dan dikirim untuk approval Manager.'
            );
    }

    public function approveDocument(
        Request $request,
        DemoContributorReadinessService $service
    ): RedirectResponse {
        $service->approve(
            $request->user(),
            ReadinessType::DOCUMENT
        );

        $result =
            $service->evaluate();

        $message =
            $result->readyForProcurement
                ? 'Document Readiness simulasi sudah APPROVED. Seluruh gate terpenuhi: READY FOR PROCUREMENT.'
                : 'Document Readiness simulasi sudah APPROVED. Contributor lain masih memiliki readiness gate yang belum selesai.';

        return redirect()
            ->route('home')
            ->with(
                'success',
                $message
            );
    }
}