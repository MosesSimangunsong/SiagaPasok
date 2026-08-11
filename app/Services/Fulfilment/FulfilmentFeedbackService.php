<?php

namespace App\Services\Fulfilment;

use App\Enums\AuditSource;
use App\Enums\FulfilmentResult;
use App\Models\DemandForecast;
use App\Models\FulfilmentFeedback;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use App\Models\ForecastDerivedStateObservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class FulfilmentFeedbackService
{
    public const AUDIT_RECORDED =
        'FULFILMENT_FEEDBACK_RECORDED';

public function __construct(
    private readonly AuditService $auditService,
    private readonly FulfilmentHandoffResolver
        $handoffResolver,
) {
}

    public function record(
        User $actor,
        DemandForecast $forecast,
        int $contributorOrganizationId,
        array $data,
    ): FulfilmentFeedback {
        $this->assertSppgActor(
            $actor
        );

        $payload =
            $this->validatePayload(
                $data
            );

        return DB::transaction(
            function () use (
                $actor,
                $forecast,
                $contributorOrganizationId,
                $payload,
            ): FulfilmentFeedback {
                /*
                 * Serialize fulfilment creation per
                 * Forecast.
                 *
                 * Unique constraint tetap menjadi
                 * database safety net.
                 */
                $currentForecast =
                    DemandForecast::query()
                        ->whereKey(
                            $forecast->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertForecastOwnedByActor(
                    $actor,
                    $currentForecast
                );

                $this->assertForecastEligibleForFulfilment(
    $currentForecast
);

                $contributor =
                    $this
                        ->resolveContributorOrganization(
                            $contributorOrganizationId
                        );

                $existing =
                    FulfilmentFeedback::query()
                        ->where(
                            'forecast_id',
                            $currentForecast->id
                        )
                        ->where(
                            'contributor_organization_id',
                            $contributor->id
                        )
                        ->exists();

                if ($existing) {
                    throw ValidationException
                        ::withMessages([
                            'contributor_organization_id' =>
                                (
                                    'Umpan Balik Pemenuhan '
                                    .'untuk contributor ini '
                                    .'sudah tercatat.'
                                ),
                        ]);
                }

                /*
                 * Planned contribution tidak pernah
                 * dihitung ulang dari current supply.
                 *
                 * Source = historical RFP handoff
                 * snapshot dari M12-02.
                 */
$handoffObservation =
    $this
        ->handoffResolver
        ->resolve(
            $currentForecast
        );

                $plannedVolume =
                    $this
                        ->resolvePlannedVolume(
                            $handoffObservation,
                            $contributor->id
                        );

                $result =
                    $this->determineResult(
                        $plannedVolume,
                        $payload[
                            'delivered_volume_decimal'
                        ]
                    );

                $this->assertReasonRequirement(
                    $result,
                    $payload['reason_note']
                );

                $feedback =
                    FulfilmentFeedback::create([
                        'forecast_id' =>
                            $currentForecast->id,

                        'contributor_organization_id' =>
                            $contributor->id,

                        'unit_id' =>
                            $currentForecast->unit_id,

                        'planned_volume_snapshot' =>
                            $plannedVolume
                                ->toString(),

                        'delivered_volume' =>
                            $payload[
                                'delivered_volume_decimal'
                            ]->toString(),

                        'fulfilment_date' =>
                            $payload[
                                'fulfilment_date'
                            ],

                        'result' =>
                            $result,

                        'reason_note' =>
                            $payload['reason_note'],

                        'recorded_by' =>
                            $actor->id,

                        'recorded_at' =>
                            CarbonImmutable::now(),
                    ]);

                $feedback->refresh();

                $auditSnapshot =
                    $this->snapshot(
                        $feedback
                    );

                /*
                 * Observation ID disimpan pada audit
                 * context, bukan menjadi field ERD
                 * baru pada feedback.
                 */
                $auditSnapshot[
                    'source_rfp_observation_id'
                ] =
                    $handoffObservation->id;

                $this->auditService->record(
                    actor:
                        $actor,

                    source:
                        AuditSource::USER,

                    action:
                        self::AUDIT_RECORDED,

                    entity:
                        $feedback,

                    previousValue:
                        null,

                    newValue:
                        $auditSnapshot,

                    reasonNote:
                        $feedback->reason_note,
                );

                return $feedback;
            }
        );
    }

    private function assertSppgActor(
        User $actor,
    ): void {
        if (
            ! $actor
                ->hasValidIdentityContext()
            || ! $actor->isSppgUser()
        ) {
            throw new AuthorizationException(
                'Hanya SPPG User yang dapat mencatat Umpan Balik Pemenuhan.'
            );
        }
    }

    private function assertForecastOwnedByActor(
        User $actor,
        DemandForecast $forecast,
    ): void {
        if (
            $forecast
                ->sppg_organization_id
            !== $actor->organization_id
        ) {
            throw new AuthorizationException(
                'Forecast tidak berada dalam scope SPPG pengguna.'
            );
        }
    }

    private function assertForecastEligibleForFulfilment(
    DemandForecast $forecast,
): void {
    if ($forecast->isClosed()) {
        return;
    }

    throw ValidationException
        ::withMessages([
            'status' =>
                (
                    'Umpan Balik Pemenuhan hanya '
                    .'dapat dicatat setelah Forecast '
                    .'berstatus CLOSED.'
                ),
        ]);
}

    private function resolveContributorOrganization(
        int $organizationId,
    ): Organization {
        if ($organizationId <= 0) {
            throw ValidationException
                ::withMessages([
                    'contributor_organization_id' =>
                        'Contributor tidak valid.',
                ]);
        }

        $organization =
            Organization::query()
                ->whereKey(
                    $organizationId
                )
                ->first();

        if (
            ! $organization
            || ! $organization->isKdkmp()
        ) {
            throw ValidationException
                ::withMessages([
                    'contributor_organization_id' =>
                        'Contributor tidak valid.',
                ]);
        }

        return $organization;
    }

    /**
     * Mengambil first snapshot-capable observation
     * dari episode RFP TRUE terakhir.
     *
     * Kenapa bukan latest TRUE row?
     *
     * Selama RFP tetap TRUE, contributor allocation
     * dapat berubah dan M12-02 dapat membuat
     * observation baru.
     *
     * Planned fulfilment harus tetap memakai
     * handoff pertama pada episode tersebut.
     *
     * Jika RFP:
     *
     * TRUE -> FALSE -> TRUE
     *
     * episode TRUE kedua menjadi handoff baru.
     */



    private function resolvePlannedVolume(
        ForecastDerivedStateObservation $observation,
        int $organizationId,
    ): FixedScaleDecimal {
        $volumes =
            $observation
                ->contributor_safe_supply_by_organization
            ?? [];

        $rawVolume =
            $volumes[$organizationId]
            ?? $volumes[
                (string) $organizationId
            ]
            ?? null;

        if ($rawVolume === null) {
            throw ValidationException
                ::withMessages([
                    'contributor_organization_id' =>
                        (
                            'Organisasi bukan contributor '
                            .'pada historical RFP handoff '
                            .'Forecast ini.'
                        ),
                ]);
        }

        try {
            $plannedVolume =
                FixedScaleDecimal::from(
                    (string) $rawVolume
                );
        } catch (
            InvalidArgumentException
        ) {
            throw ValidationException
                ::withMessages([
                    'planned_volume_snapshot' =>
                        (
                            'Historical planned volume '
                            .'tidak valid.'
                        ),
                ]);
        }

        if ($plannedVolume->isZero()) {
            throw ValidationException
                ::withMessages([
                    'planned_volume_snapshot' =>
                        (
                            'Historical planned volume '
                            .'harus lebih besar dari nol.'
                        ),
                ]);
        }

        return $plannedVolume;
    }

    private function determineResult(
        FixedScaleDecimal $plannedVolume,
        FixedScaleDecimal $deliveredVolume,
    ): FulfilmentResult {
        if ($deliveredVolume->isZero()) {
            return FulfilmentResult::FAILED;
        }

        if (
            $deliveredVolume
                ->greaterThanOrEqual(
                    $plannedVolume
                )
        ) {
            return FulfilmentResult
                ::FULFILLED;
        }

        return FulfilmentResult::PARTIAL;
    }

    private function assertReasonRequirement(
        FulfilmentResult $result,
        ?string $reason,
    ): void {
        if (
            $result
                === FulfilmentResult::FULFILLED
        ) {
            return;
        }

        if ($reason !== null) {
            return;
        }

        throw ValidationException
            ::withMessages([
                'reason_note' =>
                    (
                        'Alasan wajib diisi untuk '
                        .$result->label().'.'
                    ),
            ]);
    }

    /**
     * @return array{
     *     delivered_volume_decimal: FixedScaleDecimal,
     *     fulfilment_date: string,
     *     reason_note: ?string
     * }
     */
    private function validatePayload(
        array $data,
    ): array {
        $validated =
            Validator::make(
                $data,
                [
                    'delivered_volume' => [
                        'required',
                    ],

                    'fulfilment_date' => [
                        'required',
                        'date',
                    ],

                    'reason_note' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],
                ]
            )->validate();

        $rawDelivered =
            $validated[
                'delivered_volume'
            ];

        /*
         * Jangan menerima binary float sebagai
         * quantity authority.
         *
         * Form HTTP normal mengirim string.
         */
        if (
            ! is_string($rawDelivered)
            && ! is_int($rawDelivered)
        ) {
            throw ValidationException
                ::withMessages([
                    'delivered_volume' =>
                        (
                            'Delivered volume harus '
                            .'berupa angka desimal '
                            .'maksimal 6 digit pecahan.'
                        ),
                ]);
        }

        try {
            $deliveredVolume =
                FixedScaleDecimal::from(
                    $rawDelivered
                );
        } catch (
            InvalidArgumentException
        ) {
            throw ValidationException
                ::withMessages([
                    'delivered_volume' =>
                        (
                            'Delivered volume harus '
                            .'berupa angka non-negatif '
                            .'dengan maksimal 6 digit '
                            .'pecahan.'
                        ),
                ]);
        }

        $reason =
            isset(
                $validated['reason_note']
            )
                ? trim(
                    $validated[
                        'reason_note'
                    ]
                )
                : null;

        if ($reason === '') {
            $reason = null;
        }

        return [
            'delivered_volume_decimal' =>
                $deliveredVolume,

            'fulfilment_date' =>
                CarbonImmutable::parse(
                    $validated[
                        'fulfilment_date'
                    ]
                )->toDateString(),

            'reason_note' =>
                $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(
        FulfilmentFeedback $feedback,
    ): array {
        return [
            'id' =>
                $feedback->id,

            'forecast_id' =>
                $feedback->forecast_id,

            'contributor_organization_id' =>
                $feedback
                    ->contributor_organization_id,

            'unit_id' =>
                $feedback->unit_id,

            'planned_volume_snapshot' =>
                (string)
                $feedback
                    ->planned_volume_snapshot,

            'delivered_volume' =>
                (string)
                $feedback
                    ->delivered_volume,

            'fulfilment_date' =>
                $feedback
                    ->fulfilment_date
                    ->toDateString(),

            'result' =>
                $feedback
                    ->result
                    ->value,

            'reason_note' =>
                $feedback->reason_note,

            'recorded_by' =>
                $feedback->recorded_by,

            'recorded_at' =>
                $feedback
                    ->recorded_at
                    ->toIso8601String(),
        ];
    }
}