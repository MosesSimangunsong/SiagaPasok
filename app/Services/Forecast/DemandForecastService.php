<?php

namespace App\Services\Forecast;

use App\Enums\AuditSource;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Models\DemandForecast;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Enums\FallbackOfferStatus;
use App\Models\FallbackOffer;
use App\Services\Audit\AuditService;
use App\Services\Notification\DerivedForecastStateObservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DemandForecastService
{
    private const AUDIT_CREATED = 'FORECAST_CREATED';
    private const AUDIT_DRAFT_UPDATED = 'FORECAST_DRAFT_UPDATED';
    private const AUDIT_PUBLISHED = 'FORECAST_PUBLISHED';
    private const AUDIT_REVISED = 'FORECAST_REVISED';
    private const AUDIT_CANCELLED = 'FORECAST_CANCELLED';
    private const AUDIT_CLOSED = 'FORECAST_CLOSED';

    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
) {
}

    public function createDraft(
        User $actor,
        array $data,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        $validated = $this->validateFullPayload($data);

        return DB::transaction(function () use (
            $actor,
            $validated,
        ): DemandForecast {
            $forecast = DemandForecast::create([
                'sppg_organization_id' => $actor->organization_id,
                'commodity_id' => $validated['commodity_id'],
                'unit_id' => $validated['unit_id'],
                'forecast_code' => $this->generateForecastCode(),
                'target_volume' => $validated['target_volume'],
                'required_start_at' => $validated['required_start_at'],
                'required_end_at' => $validated['required_end_at'],
                'freshness_interval_hours' =>
                    $validated['freshness_interval_hours'] ?? null,
                'status' => ForecastStatus::DRAFT,
                'notes' => $validated['notes'] ?? null,
                'version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_CREATED,
                entity: $forecast,
                previousValue: null,
                newValue: $this->snapshot($forecast),
            );

            return $forecast;
        });
    }

    public function updateDraft(
        User $actor,
        DemandForecast $forecast,
        array $data,
        int $expectedVersion,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        $validated = $this->validateFullPayload($data);

        return DB::transaction(function () use (
            $actor,
            $forecast,
            $validated,
            $expectedVersion,
        ): DemandForecast {
            $current = DemandForecast::query()
                ->whereKey($forecast->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($actor, $current);
            $this->assertVersion($current, $expectedVersion);

            if (! $current->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Forecast DRAFT yang dapat diedit '
                        .'melalui operasi Update Draft.'
                    ),
                ]);
            }

            $before = $this->snapshot($current);

            $current->fill([
                'commodity_id' => $validated['commodity_id'],
                'unit_id' => $validated['unit_id'],
                'target_volume' => $validated['target_volume'],
                'required_start_at' => $validated['required_start_at'],
                'required_end_at' => $validated['required_end_at'],
                'freshness_interval_hours' =>
                    $validated['freshness_interval_hours'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (! $current->isDirty()) {
                return $current;
            }

            $current->version++;
            $current->updated_by = $actor->id;
            $current->save();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_DRAFT_UPDATED,
                entity: $current,
                previousValue: $before,
                newValue: $this->snapshot($current),
            );

            return $current->refresh();
        });
    }

    public function publish(
        User $actor,
        DemandForecast $forecast,
        int $expectedVersion,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        return DB::transaction(function () use (
            $actor,
            $forecast,
            $expectedVersion,
        ): DemandForecast {
            $current = DemandForecast::query()
                ->whereKey($forecast->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($actor, $current);

            /*
             * Repeat publish requests are idempotent.
             * No second audit row is created.
             */
            if ($current->isPublished()) {
                return $current;
            }

            $this->assertVersion($current, $expectedVersion);

            if (! $current->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Forecast DRAFT yang dapat dipublikasikan.'
                    ),
                ]);
            }

            $this->assertPublishable($current);

            $before = $this->snapshot($current);

            $current->update([
                'status' => ForecastStatus::PUBLISHED,
                'published_at' => now(),
                'version' => $current->version + 1,
                'updated_by' => $actor->id,
            ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_PUBLISHED,
                entity: $current,
                previousValue: $before,
                newValue: $this->snapshot($current),
            );

            /*
 * Establish initial derived observation only after
 * Forecast publish transaction successfully commits.
 *
 * Initial positive Shortfall becomes baseline and
 * does not create Shortfall notification.
 */
$this->derivedStateObservationService
    ->observeAfterCommit(
        $current
    );

            return $current->refresh();
        });
    }

    public function revisePublished(
        User $actor,
        DemandForecast $forecast,
        array $changes,
        string $reason,
        int $expectedVersion,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan revisi Forecast wajib diisi.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $forecast,
            $changes,
            $reason,
            $expectedVersion,
        ): DemandForecast {
            $current = DemandForecast::query()
                ->whereKey($forecast->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($actor, $current);
            $this->assertVersion($current, $expectedVersion);

            if (! $current->isPublished()) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Revisi Published hanya dapat dilakukan '
                        .'pada Forecast PUBLISHED.'
                    ),
                ]);
            }

            $candidate = [
                'target_volume' =>
                    $changes['target_volume']
                    ?? (string) $current->target_volume,

                'required_start_at' =>
                    $changes['required_start_at']
                    ?? $current->required_start_at->toDateTimeString(),

                'required_end_at' =>
                    $changes['required_end_at']
                    ?? $current->required_end_at->toDateTimeString(),
            ];

            $validated = Validator::make(
                $candidate,
                [
                    'target_volume' => [
                        'required',
                        'numeric',
                        'gt:0',
                    ],
                    'required_start_at' => [
                        'required',
                        'date',
                    ],
                    'required_end_at' => [
                        'required',
                        'date',
                        'after_or_equal:required_start_at',
                    ],
                ]
            )->validate();

            $before = $this->snapshot($current);

            $current->fill([
                'target_volume' => $validated['target_volume'],
                'required_start_at' =>
                    $validated['required_start_at'],
                'required_end_at' =>
                    $validated['required_end_at'],
            ]);

            if (! $current->isDirty([
                'target_volume',
                'required_start_at',
                'required_end_at',
            ])) {
                throw ValidationException::withMessages([
                    'revision' => (
                        'Tidak ada perubahan volume atau periode '
                        .'yang perlu disimpan.'
                    ),
                ]);
            }

            $current->version++;
            $current->updated_by = $actor->id;
            $current->save();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_REVISED,
                entity: $current,
                previousValue: $before,
                newValue: $this->snapshot($current),
                reasonNote: $reason,
            );

            /*
 * Target volume / requirement window / Forecast
 * version can change Shortfall, invalidate Readiness,
 * or remove Ready for Procurement.
 */
$this->derivedStateObservationService
    ->observeAfterCommit(
        $current
    );

            return $current->refresh();
        });
    }

    public function cancel(
        User $actor,
        DemandForecast $forecast,
        string $reason,
        int $expectedVersion,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => (
                    'Alasan pembatalan Forecast wajib diisi.'
                ),
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $forecast,
            $reason,
            $expectedVersion,
        ): DemandForecast {
            $current = DemandForecast::query()
                ->whereKey($forecast->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($actor, $current);

            /*
             * Repeat cancellation is idempotent.
             */
            if ($current->isCancelled()) {
                return $current;
            }

            $this->assertVersion($current, $expectedVersion);

            if (
                ! $current->isDraft()
                && ! $current->isPublished()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Forecast hanya dapat dibatalkan dari '
                        .'DRAFT atau PUBLISHED.'
                    ),
                ]);
            }

            /*
 * M07 / C29:
 *
 * Accepted fallback adalah allocation decision
 * yang tidak boleh dibatalkan sepihak melalui
 * Forecast cancellation.
 *
 * Commercial resolution berada di luar MVP.
 */
$this->assertNoAcceptedFallbackAllocation(
    $current
);

$wasPublished =
    $current->isPublished();

            $before = $this->snapshot($current);

            $current->update([
                'status' => ForecastStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'version' => $current->version + 1,
                'updated_by' => $actor->id,
            ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_CANCELLED,
                entity: $current,
                previousValue: $before,
                newValue: $this->snapshot($current),
                reasonNote: $reason,
            );

            /*
 * DRAFT -> CANCELLED tidak mempunyai operational
 * derived state yang perlu direcalculate.
 *
 * PUBLISHED -> CANCELLED dapat menyebabkan
 * Ready for Procurement TRUE -> FALSE.
 */
if ($wasPublished) {
    $this->derivedStateObservationService
        ->observeAfterCommit(
            $current
        );
}

            return $current->refresh();
        });
    }

    public function close(
        User $actor,
        DemandForecast $forecast,
        int $expectedVersion,
    ): DemandForecast {
        $this->assertSppgActor($actor);

        return DB::transaction(function () use (
            $actor,
            $forecast,
            $expectedVersion,
        ): DemandForecast {
            $current = DemandForecast::query()
                ->whereKey($forecast->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($actor, $current);

            /*
             * Repeat close is idempotent.
             */
            if ($current->isClosed()) {
                return $current;
            }

            $this->assertVersion($current, $expectedVersion);

            if (! $current->isPublished()) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Forecast PUBLISHED yang dapat ditutup.'
                    ),
                ]);
            }

            $before = $this->snapshot($current);

            $current->update([
                'status' => ForecastStatus::CLOSED,
                'closed_at' => now(),
                'version' => $current->version + 1,
                'updated_by' => $actor->id,
            ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: self::AUDIT_CLOSED,
                entity: $current,
                previousValue: $before,
                newValue: $this->snapshot($current),
            );

            $this->derivedStateObservationService
    ->observeAfterCommit(
        $current
    );
    
            return $current->refresh();
        });
    }

    private function validateFullPayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                'commodity_id' => [
                    'required',
                    'integer',
                    Rule::exists(
                        'commodities',
                        'id'
                    )->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
                ],

                'unit_id' => [
                    'required',
                    'integer',
                    Rule::exists(
                        'units',
                        'id'
                    )->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
                ],

                'target_volume' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'required_start_at' => [
                    'required',
                    'date',
                ],

                'required_end_at' => [
                    'required',
                    'date',
                    'after_or_equal:required_start_at',
                ],

                'freshness_interval_hours' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],
            ]
        )->validate();
    }


    private function assertNoAcceptedFallbackAllocation(
    DemandForecast $forecast,
): void {
    $hasAcceptedAllocation =
        FallbackOffer::query()
            ->where(
                'status',
                FallbackOfferStatus
                    ::ACCEPTED
                    ->value
            )
            ->where(
                'accepted_volume',
                '>',
                '0.000000'
            )
            ->whereHas(
                'fallbackRequest',
                fn ($query) =>
                    $query->where(
                        'forecast_id',
                        $forecast->id
                    )
            )
            ->whereHas(
                'sources',
                fn ($query) =>
                    $query->where(
                        'allocated_volume',
                        '>',
                        '0.000000'
                    )
            )
            ->exists();

    if ($hasAcceptedAllocation) {
        throw ValidationException::withMessages([
            'fallback' => (
                'Forecast tidak dapat dibatalkan '
                .'karena sudah memiliki accepted '
                .'fallback allocation. Allocation '
                .'tersebut harus diselesaikan melalui '
                .'prosedur operasional/manual di luar '
                .'SiagaPasok terlebih dahulu.'
            ),
        ]);
    }
}
    private function assertPublishable(
        DemandForecast $forecast,
    ): void {
        /*
         * Revalidate current persisted business payload.
         * This also catches commodity/unit that became inactive
         * after the Draft was originally created.
         */
        $this->validateFullPayload([
            'commodity_id' => $forecast->commodity_id,
            'unit_id' => $forecast->unit_id,
            'target_volume' => (string) $forecast->target_volume,
            'required_start_at' =>
                $forecast->required_start_at?->toDateTimeString(),
            'required_end_at' =>
                $forecast->required_end_at?->toDateTimeString(),
            'freshness_interval_hours' =>
                $forecast->freshness_interval_hours,
            'notes' => $forecast->notes,
        ]);

        /*
         * Published Forecast must have an active PRIMARY
         * orchestration path.
         */
        $hasActivePrimary = SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast->sppg_organization_id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->whereHas(
                'kdkmpOrganization',
                fn ($query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->exists();

        if (! $hasActivePrimary) {
            throw ValidationException::withMessages([
                'network' => (
                    'Forecast belum dapat dipublikasikan karena '
                    .'SPPG belum memiliki KDKMP PRIMARY aktif.'
                ),
            ]);
        }
    }

    private function assertSppgActor(User $actor): void
    {
        if (
            ! $actor->isSppgUser()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya SPPG User aktif yang dapat mengelola Forecast.'
            );
        }
    }

    private function assertOwner(
        User $actor,
        DemandForecast $forecast,
    ): void {
        if (
            $actor->organization_id
            !== $forecast->sppg_organization_id
        ) {
            throw new AuthorizationException(
                'Forecast tersebut bukan milik organisasi SPPG Anda.'
            );
        }
    }

    private function assertVersion(
        DemandForecast $forecast,
        int $expectedVersion,
    ): void {
        if ($forecast->version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'version' => (
                    'Forecast telah berubah sejak terakhir dibuka. '
                    .'Muat ulang data sebelum melanjutkan.'
                ),
            ]);
        }
    }

    private function generateForecastCode(): string
    {
        do {
            /*
             * Technical identifier only.
             * No operational meaning is encoded into this code.
             */
            $code = 'FRC-'.Str::upper(
                (string) Str::ulid()
            );
        } while (
            DemandForecast::query()
                ->where('forecast_code', $code)
                ->exists()
        );

        return $code;
    }

    private function snapshot(
        DemandForecast $forecast,
    ): array {
        return [
            'id' => $forecast->id,
            'forecast_code' => $forecast->forecast_code,
            'sppg_organization_id' =>
                $forecast->sppg_organization_id,
            'commodity_id' => $forecast->commodity_id,
            'unit_id' => $forecast->unit_id,
            'target_volume' =>
                (string) $forecast->target_volume,
            'required_start_at' =>
                $forecast->required_start_at
                    ?->toIso8601String(),
            'required_end_at' =>
                $forecast->required_end_at
                    ?->toIso8601String(),
            'freshness_interval_hours' =>
                $forecast->freshness_interval_hours,
            'status' => $forecast->status->value,
            'notes' => $forecast->notes,
            'published_at' =>
                $forecast->published_at
                    ?->toIso8601String(),
            'closed_at' =>
                $forecast->closed_at
                    ?->toIso8601String(),
            'cancelled_at' =>
                $forecast->cancelled_at
                    ?->toIso8601String(),
            'cancellation_reason' =>
                $forecast->cancellation_reason,
            'version' => $forecast->version,
            'created_by' => $forecast->created_by,
            'updated_by' => $forecast->updated_by,
        ];
    }
}