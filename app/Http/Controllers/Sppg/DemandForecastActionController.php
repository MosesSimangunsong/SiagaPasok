<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sppg\CancelForecastRequest;
use App\Http\Requests\Sppg\ForecastVersionRequest;
use App\Http\Requests\Sppg\RevisePublishedForecastRequest;
use App\Models\DemandForecast;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DemandForecastActionController extends Controller
{
    public function publish(
        ForecastVersionRequest $request,
        DemandForecast $forecast,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'publish',
            $forecast
        );

        $updated = $service->publish(
            $request->user(),
            $forecast,
            (int) $request->validated('version'),
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $updated
            )
            ->with(
                'success',
                'Forecast berhasil dipublikasikan.'
            );
    }

    public function revise(
        RevisePublishedForecastRequest $request,
        DemandForecast $forecast,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'revise',
            $forecast
        );

        $data = $request->validated();

        $changes = array_filter(
            [
                'target_volume' =>
                    $data['target_volume'] ?? null,

                'required_start_at' =>
                    $data['required_start_at'] ?? null,

                'required_end_at' =>
                    $data['required_end_at'] ?? null,
            ],
            fn ($value) => $value !== null
        );

        $updated = $service->revisePublished(
            $request->user(),
            $forecast,
            $changes,
            $data['reason'],
            (int) $data['version'],
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $updated
            )
            ->with(
                'success',
                'Forecast berhasil direvisi.'
            );
    }

    public function cancel(
        CancelForecastRequest $request,
        DemandForecast $forecast,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'cancel',
            $forecast
        );

        $data = $request->validated();

        $updated = $service->cancel(
            $request->user(),
            $forecast,
            $data['cancellation_reason'],
            (int) $data['version'],
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $updated
            )
            ->with(
                'success',
                'Forecast berhasil dibatalkan.'
            );
    }

    public function close(
        ForecastVersionRequest $request,
        DemandForecast $forecast,
        DemandForecastService $service
    ): RedirectResponse {
        Gate::authorize(
            'close',
            $forecast
        );

        $updated = $service->close(
            $request->user(),
            $forecast,
            (int) $request->validated('version'),
        );

        return redirect()
            ->route(
                'sppg.forecasts.show',
                $updated
            )
            ->with(
                'success',
                'Forecast berhasil ditutup.'
            );
    }
}