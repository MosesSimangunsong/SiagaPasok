<?php

namespace App\Services\Readiness;

use App\Enums\DocumentStatus;
use App\Enums\ReadinessType;
use App\Models\DemandForecast;
use App\Models\DocumentRecord;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class DocumentRecordValidityService
{
    public function assertMatchesItem(
        DocumentRecord $documentRecord,
        ReadinessItem $item,
        ReadinessChecklist $checklist,
    ): void {
        $item->loadMissing(
            'requirement'
        );

        $documentRecord->loadMissing(
            'requirement'
        );

        if (
            $checklist->readiness_type
            !== ReadinessType::DOCUMENT
        ) {
            throw ValidationException::withMessages([
                'document_record_id' => (
                    'Document Record hanya dapat digunakan '
                    .'pada Document Readiness.'
                ),
            ]);
        }

        if (
            $item->readiness_checklist_id
            !== $checklist->id
        ) {
            throw ValidationException::withMessages([
                'item' => (
                    'Readiness Item bukan bagian dari '
                    .'Checklist tersebut.'
                ),
            ]);
        }

        if (
            $documentRecord->organization_id
            !== $checklist->organization_id
        ) {
            throw ValidationException::withMessages([
                'document_record_id' => (
                    'Document Record tidak tersedia untuk '
                    .'organisasi Checklist tersebut.'
                ),
            ]);
        }

        if (
            $documentRecord->requirement_id
            !== $item->requirement_id
        ) {
            throw ValidationException::withMessages([
                'document_record_id' => (
                    'Document Record tidak sesuai dengan '
                    .'requirement pada Readiness Item.'
                ),
            ]);
        }

        if (
            ! $item->requirement
            || $item->requirement->readiness_type
                !== ReadinessType::DOCUMENT
        ) {
            throw ValidationException::withMessages([
                'requirement' => (
                    'Readiness Item tidak memiliki '
                    .'Document Requirement yang valid.'
                ),
            ]);
        }

        if (
            ! $documentRecord->requirement
            || $documentRecord
                ->requirement
                ->readiness_type
                !== ReadinessType::DOCUMENT
        ) {
            throw ValidationException::withMessages([
                'document_record_id' => (
                    'Document Record tidak terhubung ke '
                    .'Document Requirement yang valid.'
                ),
            ]);
        }
    }

public function assertEffectiveForForecast(
    DocumentRecord $documentRecord,
    ReadinessItem $item,
    ReadinessChecklist $checklist,
    DemandForecast $forecast,
    ?int $expectedRevisionNo = null,
    ?CarbonInterface $evaluatedAt = null,
): void {
    $this->assertMatchesItem(
        $documentRecord,
        $item,
        $checklist
    );

    $evaluationTime =
    $evaluatedAt === null
        ? CarbonImmutable::now()
        : CarbonImmutable::instance(
            $evaluatedAt
        );

    if (
        $expectedRevisionNo !== null
        && $documentRecord->revision_no
            !== $expectedRevisionNo
    ) {
        throw ValidationException::withMessages([
            'document_record_id' => (
                'Document Record telah berubah '
                .'setelah payload Readiness '
                .'dibekukan.'
            ),
        ]);
    }

    /*
 * Expiry merupakan derived time validity.
 *
 * Equality pada expires_at masih valid.
 * Dokumen baru expired ketika evaluation instant
 * benar-benar melewati expires_at.
 *
 * Ini konsisten dengan existing required-period
 * contract: expires_at == required_end_at masih
 * dianggap mencakup periode Forecast.
 */
if (
    $documentRecord->expires_at !== null
    && $evaluationTime->gt(
        CarbonImmutable::instance(
            $documentRecord->expires_at
        )
    )
) {
    throw ValidationException::withMessages([
        'document_record_id' => (
            'Document Record telah melewati '
            .'masa berlaku pada waktu evaluasi.'
        ),
    ]);
}

    if (
        $documentRecord->status
        !== DocumentStatus::VALID
    ) {
        throw ValidationException::withMessages([
            'document_record_id' => (
                'Document Record harus '
                .'berstatus VALID.'
            ),
        ]);
    }

    if (
        $documentRecord->valid_from !== null
        && $documentRecord
            ->valid_from
            ->gt(
                $forecast->required_start_at
            )
    ) {
        throw ValidationException::withMessages([
            'document_record_id' => (
                'Document Record belum berlaku '
                .'pada awal periode kebutuhan '
                .'Forecast.'
            ),
        ]);
    }

    if (
        $documentRecord->expires_at !== null
        && $documentRecord
            ->expires_at
            ->lt(
                $forecast->required_end_at
            )
    ) {
        throw ValidationException::withMessages([
            'document_record_id' => (
                'Document Record tidak berlaku '
                .'sampai akhir periode kebutuhan '
                .'Forecast.'
            ),
        ]);
    }
}

public function isEffectiveForForecast(
    DocumentRecord $documentRecord,
    ReadinessItem $item,
    ReadinessChecklist $checklist,
    DemandForecast $forecast,
    ?int $expectedRevisionNo = null,
    ?CarbonInterface $evaluatedAt = null,
): bool {
    try {
$this->assertEffectiveForForecast(
    $documentRecord,
    $item,
    $checklist,
    $forecast,
    $expectedRevisionNo,
    $evaluatedAt
);

        return true;
    } catch (ValidationException) {
        return false;
    }
}
} 