<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ReadinessType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\CreateReadinessRevisionRequest;
use App\Http\Requests\Kdkmp\PrepareReadinessChecklistRequest;
use App\Http\Requests\Kdkmp\SubmitReadinessChecklistRequest;
use App\Http\Requests\Kdkmp\UpdateReadinessItemRequest;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistRevisionService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use App\Services\Readiness\ReadinessEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReadinessController extends Controller
{
    public function __construct(
        private readonly ReadinessChecklistPreparationService $preparationService,
        private readonly ReadinessChecklistWorkflowService $workflowService,
        private readonly ReadinessChecklistRevisionService $revisionService,
        private readonly ReadinessEvaluationService $evaluationService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            ReadinessChecklist::class
        );

        $user =
            request()->user();

        $checklists =
            ReadinessChecklist::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->with([
                    'forecast.sppgOrganization',
                    'forecast.commodity',
                    'forecast.unit',
                    'preparedBy',
                    'submittedBy',
                    'reviewedBy',
                ])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (
                        ReadinessChecklist $checklist
                    ): array =>
                        $this->serializeChecklistSummary(
                            $checklist
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Readiness/Index',
            [
                'checklists' =>
                    $checklists,
            ]
        );
    }

    public function show(
        ReadinessChecklist $checklist
    ): Response {
        Gate::authorize(
            'view',
            $checklist
        );

        $checklist->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',
            'organization',
            'preparedBy',
            'submittedBy',
            'reviewedBy',
            'supersedesChecklist',
            'items.requirement',
            'items.documentRecord',
            'items.updatedBy',
        ]);

        $evaluation =
            $this->evaluationService
                ->evaluateContributor(
                    $checklist->forecast,
                    $checklist->organization_id
                );

        $user =
            request()->user();

        return Inertia::render(
            'Kdkmp/Readiness/Show',
            [
                'checklist' =>
                    $this->serializeChecklist(
                        $checklist
                    ),

                'readiness' => [
                    'is_contributor' =>
                        $evaluation->isContributor,

                    'logistics_ready' =>
                        $evaluation->logisticsReady,

                    'document_ready' =>
                        $evaluation->documentReady,

                    'logistics_reason_codes' =>
                        $evaluation
                            ->logisticsReasonCodes,

                    'document_reason_codes' =>
                        $evaluation
                            ->documentReasonCodes,
                ],

                'can' => [
                    'update_item' =>
                        $user->can(
                            'updateItem',
                            $checklist
                        ),

                    'submit' =>
                        $user->can(
                            'submit',
                            $checklist
                        ),

                    'create_revision' =>
                        $user->can(
                            'createRevision',
                            $checklist
                        ),
                ],
            ]
        );
    }

    public function prepare(
        PrepareReadinessChecklistRequest $request,
        DemandForecast $forecast,
        string $type
    ): RedirectResponse {
        $readinessType =
            $this->resolveType(
                $type
            );

        $checklist =
            $this->preparationService
                ->createInitialDraft(
                    $request->user(),
                    $forecast,
                    $readinessType
                );

        return redirect()
            ->route(
                'kdkmp.readiness.show',
                $checklist
            )
            ->with(
                'success',
                'Checklist readiness berhasil disiapkan.'
            );
    }

    public function updateItem(
        UpdateReadinessItemRequest $request,
        ReadinessChecklist $checklist,
        ReadinessItem $item
    ): RedirectResponse {
        $this->assertItemBelongsToChecklist(
            $checklist,
            $item
        );

        $this->workflowService
            ->updateItem(
                $request->user(),
                $checklist,
                $item,
                $request->validated()
            );

        return redirect()
            ->route(
                'kdkmp.readiness.show',
                $checklist
            )
            ->with(
                'success',
                'Item readiness berhasil diperbarui.'
            );
    }

    public function submit(
        SubmitReadinessChecklistRequest $request,
        ReadinessChecklist $checklist
    ): RedirectResponse {
        $this->workflowService
            ->submit(
                $request->user(),
                $checklist
            );

        return redirect()
            ->route(
                'kdkmp.readiness.show',
                $checklist
            )
            ->with(
                'success',
                'Readiness berhasil diajukan kepada Manager.'
            );
    }

    public function createRevision(
        CreateReadinessRevisionRequest $request,
        ReadinessChecklist $checklist
    ): RedirectResponse {
        $revision =
            $this->revisionService
                ->createRevision(
                    $request->user(),
                    $checklist
                );

        return redirect()
            ->route(
                'kdkmp.readiness.show',
                $revision
            )
            ->with(
                'success',
                'Revision readiness baru berhasil dibuat.'
            );
    }

    private function resolveType(
        string $type
    ): ReadinessType {
        $resolved =
            ReadinessType::tryFrom(
                strtoupper(
                    $type
                )
            );

        abort_if(
            $resolved === null,
            404
        );

        return $resolved;
    }

    private function assertItemBelongsToChecklist(
        ReadinessChecklist $checklist,
        ReadinessItem $item
    ): void {
        abort_if(
            $item->readiness_checklist_id
                !== $checklist->id,
            404
        );
    }

    private function serializeChecklistSummary(
        ReadinessChecklist $checklist
    ): array {
        return [
            'id' =>
                $checklist->id,

            'readiness_type' =>
                $checklist
                    ->readiness_type
                    ->value,

            'version_no' =>
                $checklist->version_no,

            'forecast_version' =>
                $checklist->forecast_version,

            'status' =>
                $checklist
                    ->status
                    ->value,

            'is_current_version' =>
                (bool)
                $checklist
                    ->is_current_version,

            'forecast' =>
                $this->serializeForecast(
                    $checklist
                ),

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

    private function serializeChecklist(
        ReadinessChecklist $checklist
    ): array {
        return [
            ...$this->serializeChecklistSummary(
                $checklist
            ),

            'organization' => [
                'id' =>
                    $checklist
                        ->organization
                        ->id,

                'code' =>
                    $checklist
                        ->organization
                        ->code,

                'name' =>
                    $checklist
                        ->organization
                        ->name,
            ],

            'supersedes_checklist_id' =>
                $checklist
                    ->supersedes_checklist_id,

            'prepared_by' =>
                $this->serializeUser(
                    $checklist
                        ->preparedBy
                ),

            'submitted_by' =>
                $this->serializeUser(
                    $checklist
                        ->submittedBy
                ),

            'reviewed_by' =>
                $this->serializeUser(
                    $checklist
                        ->reviewedBy
                ),

            'submitted_at' =>
                $checklist
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_at' =>
                $checklist
                    ->reviewed_at
                    ?->toIso8601String(),

            'approved_at' =>
                $checklist
                    ->approved_at
                    ?->toIso8601String(),

            'review_reason' =>
                $checklist
                    ->review_reason,

            'items' =>
                $checklist
                    ->items
                    ->map(
                        fn (
                            ReadinessItem $item
                        ): array => [
                            'id' =>
                                $item->id,

                            'requirement_id' =>
                                $item
                                    ->requirement_id,

                            'requirement' => [
                                'code' =>
                                    $item
                                        ->requirement
                                        ->requirement_code,

                                'label' =>
                                    $item
                                        ->requirement
                                        ->label,

                                'scope' =>
                                    $item
                                        ->requirement
                                        ->requirement_scope
                                        ->value,
                            ],

                            'is_required' =>
                                (bool)
                                $item
                                    ->is_required,

                            'is_satisfied' =>
                                (bool)
                                $item
                                    ->is_satisfied,

                            'note' =>
                                $item->note,

                            'value_json' =>
                                $item
                                    ->value_json,

                            'document_record_id' =>
                                $item
                                    ->document_record_id,

                            'document_record_revision_no' =>
                                $item
                                    ->document_record_revision_no,

                            'document_record' =>
                                $item
                                    ->documentRecord
                                    ? [
                                        'id' =>
                                            $item
                                                ->documentRecord
                                                ->id,

                                        'document_name' =>
                                            $item
                                                ->documentRecord
                                                ->document_name,

                                        'reference_number' =>
                                            $item
                                                ->documentRecord
                                                ->reference_number,

                                        'status' =>
                                            $item
                                                ->documentRecord
                                                ->status
                                                ->value,

                                        'revision_no' =>
                                            $item
                                                ->documentRecord
                                                ->revision_no,

                                        'valid_from' =>
                                            $item
                                                ->documentRecord
                                                ->valid_from
                                                ?->toIso8601String(),

                                        'expires_at' =>
                                            $item
                                                ->documentRecord
                                                ->expires_at
                                                ?->toIso8601String(),
                                    ]
                                    : null,
                        ]
                    )
                    ->values(),
        ];
    }

    private function serializeForecast(
        ReadinessChecklist $checklist
    ): array {
        $forecast =
            $checklist->forecast;

        return [
            'id' =>
                $forecast->id,

            'forecast_code' =>
                $forecast
                    ->forecast_code,

            'version' =>
                $forecast->version,

            'sppg_name' =>
                $forecast
                    ->sppgOrganization
                    ->name,

            'commodity_name' =>
                $forecast
                    ->commodity
                    ->name,

            'target_volume' =>
                (string)
                $forecast
                    ->target_volume,

            'unit_symbol' =>
                $forecast
                    ->unit
                    ->symbol,

            'required_start_at' =>
                $forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast
                    ->required_end_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeUser(
        $user
    ): ?array {
        if (! $user) {
            return null;
        }

        return [
            'id' =>
                $user->id,

            'name' =>
                $user->name,
        ];
    }
}