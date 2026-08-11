<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ReadinessType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\RevokeDocumentRecordRequest;
use App\Http\Requests\Kdkmp\StoreDocumentRecordRequest;
use App\Http\Requests\Kdkmp\UpdateDocumentRecordRequest;
use App\Http\Requests\Kdkmp\ValidateDocumentRecordRequest;
use App\Models\DocumentRecord;
use App\Models\ReadinessRequirement;
use App\Services\Readiness\DocumentRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRecordController extends Controller
{
    public function __construct(
        private readonly DocumentRecordService $documentService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            DocumentRecord::class
        );

        $user =
            request()->user();

        $records =
            DocumentRecord::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->with([
                    'requirement',
                    'createdBy',
                ])
                ->orderBy('document_name')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        DocumentRecord $record
                    ): array =>
                        $this->serializeRecord(
                            $record
                        )
                )
                ->values();

        $requirements =
            ReadinessRequirement::query()
                ->where(
                    'readiness_type',
                    ReadinessType::DOCUMENT->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'applies_to_organization_type',
                    $user
                        ->organization
                        ->organization_type
                        ->value
                )
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        ReadinessRequirement $requirement
                    ): array => [
                        'id' =>
                            $requirement->id,

                        'requirement_code' =>
                            $requirement
                                ->requirement_code,

                        'label' =>
                            $requirement
                                ->label,

                        'scope' =>
                            $requirement
                                ->requirement_scope
                                ->value,
                    ]
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Documents/Index',
            [
                'records' =>
                    $records,

                'requirements' =>
                    $requirements,
            ]
        );
    }

    public function store(
        StoreDocumentRecordRequest $request
    ): RedirectResponse {
        $validated =
            $request->validated();

        $requirement =
            ReadinessRequirement::query()
                ->findOrFail(
                    $validated[
                        'requirement_id'
                    ]
                );

        unset(
            $validated[
                'requirement_id'
            ]
        );

        $this->documentService
            ->create(
                $request->user(),
                $requirement,
                $validated
            );

        return redirect()
            ->route(
                'kdkmp.documents.index'
            )
            ->with(
                'success',
                'Document Record berhasil dibuat.'
            );
    }

    public function update(
        UpdateDocumentRecordRequest $request,
        DocumentRecord $documentRecord
    ): RedirectResponse {
        $this->documentService
            ->update(
                $request->user(),
                $documentRecord,
                $request->validated()
            );

        return redirect()
            ->route(
                'kdkmp.documents.index'
            )
            ->with(
                'success',
                'Document Record berhasil diperbarui.'
            );
    }

    public function markValid(
        ValidateDocumentRecordRequest $request,
        DocumentRecord $documentRecord
    ): RedirectResponse {
        $this->documentService
            ->markValid(
                $request->user(),
                $documentRecord
            );

        return redirect()
            ->route(
                'kdkmp.documents.index'
            )
            ->with(
                'success',
                'Document Record ditandai VALID.'
            );
    }

    public function revoke(
        RevokeDocumentRecordRequest $request,
        DocumentRecord $documentRecord
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->documentService
            ->revoke(
                $request->user(),
                $documentRecord,
                $validated['reason']
            );

        return redirect()
            ->route(
                'kdkmp.documents.index'
            )
            ->with(
                'success',
                'Document Record berhasil direvoke.'
            );
    }

    private function serializeRecord(
        DocumentRecord $record
    ): array {
        return [
            'id' =>
                $record->id,

            'requirement' => [
                'id' =>
                    $record
                        ->requirement
                        ->id,

                'requirement_code' =>
                    $record
                        ->requirement
                        ->requirement_code,

                'label' =>
                    $record
                        ->requirement
                        ->label,
            ],

            'document_name' =>
                $record
                    ->document_name,

            'reference_number' =>
                $record
                    ->reference_number,

            'valid_from' =>
                $record
                    ->valid_from
                    ?->toIso8601String(),

            'expires_at' =>
                $record
                    ->expires_at
                    ?->toIso8601String(),

            'status' =>
                $record
                    ->status
                    ->value,

            'revision_no' =>
                $record
                    ->revision_no,

            'notes' =>
                $record->notes,

            'created_by' =>
                $record
                    ->createdBy
                    ? [
                        'id' =>
                            $record
                                ->createdBy
                                ->id,

                        'name' =>
                            $record
                                ->createdBy
                                ->name,
                    ]
                    : null,
        ];
    }
}