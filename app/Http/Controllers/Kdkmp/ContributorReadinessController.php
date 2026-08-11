<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ReadinessType;
use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Services\Readiness\ReadinessEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ContributorReadinessController extends Controller
{
    public function __construct(
        private readonly ReadinessEvaluationService
            $readinessEvaluationService,
    ) {
    }

    public function __invoke(
        Request $request,
        DemandForecast $forecast,
    ): Response {
        Gate::authorize(
            'viewAny',
            ReadinessChecklist::class
        );

        $user =
            $request->user();

        $evaluation =
            $this
                ->readinessEvaluationService
                ->evaluateContributor(
                    $forecast,
                    (int) $user->organization_id
                );

        /*
         * Surface ini bukan generic Forecast reader.
         *
         * Hanya current contributor yang boleh
         * memperoleh contributor readiness context.
         */
        abort_unless(
            $evaluation->isContributor,
            404
        );

        $forecast->load([
            'sppgOrganization',
            'commodity',
            'unit',
        ]);

        $checklists =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->get()
                ->keyBy(
                    fn (
                        ReadinessChecklist $checklist
                    ): string =>
                        $checklist
                            ->readiness_type
                            ->value
                );

        return Inertia::render(
            'Kdkmp/ContributorReadiness/Show',
            [
                'forecast' => [
                    'id' =>
                        $forecast->id,

                    'forecast_code' =>
                        $forecast
                            ->forecast_code,

                    'version' =>
                        $forecast->version,

                    'sppg' => [
                        'id' =>
                            $forecast
                                ->sppgOrganization
                                ->id,

                        'code' =>
                            $forecast
                                ->sppgOrganization
                                ->code,

                        'name' =>
                            $forecast
                                ->sppgOrganization
                                ->name,
                    ],

                    'commodity' => [
                        'id' =>
                            $forecast
                                ->commodity
                                ->id,

                        'code' =>
                            $forecast
                                ->commodity
                                ->code,

                        'name' =>
                            $forecast
                                ->commodity
                                ->name,
                    ],

                    'unit' => [
                        'id' =>
                            $forecast
                                ->unit
                                ->id,

                        'name' =>
                            $forecast
                                ->unit
                                ->name,

                        'symbol' =>
                            $forecast
                                ->unit
                                ->symbol,

                        'decimal_precision' =>
                            $forecast
                                ->unit
                                ->decimal_precision,
                    ],

                    'required_start_at' =>
                        $forecast
                            ->required_start_at
                            ?->toIso8601String(),

                    'required_end_at' =>
                        $forecast
                            ->required_end_at
                            ?->toIso8601String(),
                ],

                'readiness' =>
                    $evaluation
                        ->toArray(),

                'checklists' => [
                    'logistics' =>
                        $this->serializeChecklist(
                            $checklists->get(
                                ReadinessType::LOGISTICS
                                    ->value
                            )
                        ),

                    'document' =>
                        $this->serializeChecklist(
                            $checklists->get(
                                ReadinessType::DOCUMENT
                                    ->value
                            )
                        ),
                ],
            ]
        );
    }

    private function serializeChecklist(
        ?ReadinessChecklist $checklist,
    ): ?array {
        if (! $checklist) {
            return null;
        }

        return [
            'id' =>
                $checklist->id,

            'readiness_type' =>
                $checklist
                    ->readiness_type
                    ->value,

            'version_no' =>
                $checklist
                    ->version_no,

            'forecast_version' =>
                $checklist
                    ->forecast_version,

            'status' =>
                $checklist
                    ->status
                    ->value,

            'is_current_version' =>
                (bool)
                $checklist
                    ->is_current_version,

            'submitted_at' =>
                $checklist
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $checklist
                    ->approved_at
                    ?->toIso8601String(),
        ];
    }
}