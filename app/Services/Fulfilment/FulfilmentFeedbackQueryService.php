<?php

namespace App\Services\Fulfilment;

use App\Enums\ForecastStatus;
use App\Models\DemandForecast;
use App\Models\FulfilmentFeedback;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class FulfilmentFeedbackQueryService
{
    public function __construct(
        private readonly FulfilmentHandoffResolver
            $handoffResolver,
    ) {
    }

    public function sppgIndex(
        User $user,
    ): array {
        $this->assertSppgUser(
            $user
        );

        return DemandForecast::query()
            ->where(
                'sppg_organization_id',
                $user->organization_id
            )
            ->where(
                'status',
                ForecastStatus::CLOSED->value
            )
            ->with([
                'commodity',
                'unit',
            ])
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->get()
            ->map(
                function (
                    DemandForecast $forecast
                ): ?array {
                    $handoff =
                        $this
                            ->handoffResolver
                            ->resolveOrNull(
                                $forecast
                            );

                    if (! $handoff) {
                        return null;
                    }

                    $contributorIds =
                        $this
                            ->contributorIds(
                                $handoff
                                    ->contributor_safe_supply_by_organization
                                ?? []
                            );

                    $recordedCount =
                        FulfilmentFeedback::query()
                            ->where(
                                'forecast_id',
                                $forecast->id
                            )
                            ->whereIn(
                                'contributor_organization_id',
                                $contributorIds
                            )
                            ->count();

                    return [
                        'id' =>
                            $forecast->id,

                        'forecast_code' =>
                            $forecast
                                ->forecast_code,

                        'commodity' => [
                            'id' =>
                                $forecast
                                    ->commodity
                                    ->id,

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
                        ],

                        'required_start_at' =>
                            $forecast
                                ->required_start_at
                                ?->toIso8601String(),

                        'required_end_at' =>
                            $forecast
                                ->required_end_at
                                ?->toIso8601String(),

                        'closed_at' =>
                            $forecast
                                ->closed_at
                                ?->toIso8601String(),

                        'contributor_count' =>
                            count(
                                $contributorIds
                            ),

                        'feedback_recorded_count' =>
                            $recordedCount,

                        'feedback_pending_count' =>
                            max(
                                0,
                                count(
                                    $contributorIds
                                )
                                - $recordedCount
                            ),

                        'handoff_observation_id' =>
                            $handoff->id,
                    ];
                }
            )
            ->filter()
            ->values()
            ->all();
    }

    public function sppgForecast(
        User $user,
        DemandForecast $forecast,
    ): array {
        $this->assertSppgUser(
            $user
        );

        if (
            $forecast
                ->sppg_organization_id
            !== $user->organization_id
        ) {
            throw new AuthorizationException(
                'Forecast berada di luar scope SPPG pengguna.'
            );
        }

        if (! $forecast->isClosed()) {
            throw ValidationException
                ::withMessages([
                    'status' =>
                        (
                            'Umpan Balik Pemenuhan '
                            .'hanya tersedia untuk '
                            .'Forecast CLOSED.'
                        ),
                ]);
        }

        $forecast->load([
            'commodity',
            'unit',
        ]);

        $handoff =
            $this
                ->handoffResolver
                ->resolve(
                    $forecast
                );

        $volumes =
            $handoff
                ->contributor_safe_supply_by_organization
            ?? [];

        $contributorIds =
            $this->contributorIds(
                $volumes
            );

        $organizations =
            Organization::query()
                ->whereIn(
                    'id',
                    $contributorIds
                )
                ->get([
                    'id',
                    'code',
                    'name',
                ])
                ->keyBy('id');

        $feedbacks =
            FulfilmentFeedback::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->whereIn(
                    'contributor_organization_id',
                    $contributorIds
                )
                ->with([
                    'recordedBy',
                ])
                ->get()
                ->keyBy(
                    'contributor_organization_id'
                );

        $contributors = [];

        foreach (
            $contributorIds
            as $organizationId
        ) {
            $organization =
                $organizations->get(
                    $organizationId
                );

            /*
             * Historical snapshot hanya boleh
             * mengekspos Organization-level
             * identity. Tidak ada Producer,
             * Commitment atau source Offer.
             */
            if (! $organization) {
                continue;
            }

            $feedback =
                $feedbacks->get(
                    $organizationId
                );

            $rawVolume =
                $volumes[
                    $organizationId
                ]
                ?? $volumes[
                    (string)
                    $organizationId
                ]
                ?? null;

            if ($rawVolume === null) {
                continue;
            }

            $contributors[] = [
                'organization' => [
                    'id' =>
                        $organization->id,

                    'code' =>
                        $organization->code,

                    'name' =>
                        $organization->name,
                ],

                'planned_volume_snapshot' =>
                    (string) $rawVolume,

                'feedback' =>
                    $feedback
                        ? $this
                            ->serializeFeedback(
                                $feedback
                            )
                        : null,

                'can_record' =>
                    $feedback === null,
            ];
        }

        return [
            'forecast' => [
                'id' =>
                    $forecast->id,

                'forecast_code' =>
                    $forecast
                        ->forecast_code,

                'status' =>
                    $forecast
                        ->status
                        ->value,

                'commodity' => [
                    'id' =>
                        $forecast
                            ->commodity
                            ->id,

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

                'closed_at' =>
                    $forecast
                        ->closed_at
                        ?->toIso8601String(),
            ],

            'handoff' => [
                'observation_id' =>
                    $handoff->id,

                'evaluated_at' =>
                    $handoff
                        ->evaluated_at
                        ?->toIso8601String(),
            ],

            'contributors' =>
                $contributors,

            'summary' => [
                'contributor_count' =>
                    count(
                        $contributors
                    ),

                'recorded_count' =>
                    collect(
                        $contributors
                    )
                        ->whereNotNull(
                            'feedback'
                        )
                        ->count(),

                'pending_count' =>
                    collect(
                        $contributors
                    )
                        ->whereNull(
                            'feedback'
                        )
                        ->count(),
            ],
        ];
    }

    public function kdkmpIndex(
        User $user,
    ): array {
        $this->assertKdkmpUser(
            $user
        );

        return FulfilmentFeedback::query()
            ->where(
                'contributor_organization_id',
                $user->organization_id
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.commodity',
                'unit',
                'recordedBy',
            ])
            ->orderByDesc(
                'recorded_at'
            )
            ->orderByDesc('id')
            ->get()
            ->map(
                fn (
                    FulfilmentFeedback $feedback
                ): array =>
                    $this
                        ->serializeFeedbackForKdkmp(
                            $feedback
                        )
            )
            ->values()
            ->all();
    }

    public function kdkmpFeedback(
        User $user,
        FulfilmentFeedback $feedback,
    ): array {
        $this->assertKdkmpUser(
            $user
        );

        if (
            $feedback
                ->contributor_organization_id
            !== $user->organization_id
        ) {
            throw new AuthorizationException(
                'Fulfilment berada di luar scope organisasi pengguna.'
            );
        }

        $feedback->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'unit',
            'recordedBy',
        ]);

        return $this
            ->serializeFeedbackForKdkmp(
                $feedback
            );
    }

    private function assertSppgUser(
        User $user,
    ): void {
        if (
            ! $user
                ->hasValidIdentityContext()
            || ! $user->isSppgUser()
        ) {
            throw new AuthorizationException();
        }
    }

    private function assertKdkmpUser(
        User $user,
    ): void {
        if (
            ! $user
                ->hasValidIdentityContext()
            || ! $user->belongsToKdkmp()
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @param array<int|string, mixed> $volumes
     *
     * @return array<int, int>
     */
    private function contributorIds(
        array $volumes,
    ): array {
        $ids =
            array_map(
                'intval',
                array_keys(
                    $volumes
                )
            );

        $ids =
            array_values(
                array_unique(
                    $ids
                )
            );

        sort(
            $ids,
            SORT_NUMERIC
        );

        return $ids;
    }

    private function serializeFeedback(
        FulfilmentFeedback $feedback,
    ): array {
        return [
            'id' =>
                $feedback->id,

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

            'result_label' =>
                $feedback
                    ->result
                    ->label(),

            'reason_note' =>
                $feedback
                    ->reason_note,

            'recorded_by' =>
                $feedback
                    ->recordedBy
                    ? [
                        'id' =>
                            $feedback
                                ->recordedBy
                                ->id,

                        'name' =>
                            $feedback
                                ->recordedBy
                                ->name,
                    ]
                    : null,

            'recorded_at' =>
                $feedback
                    ->recorded_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeFeedbackForKdkmp(
        FulfilmentFeedback $feedback,
    ): array {
        return [
            ...$this
                ->serializeFeedback(
                    $feedback
                ),

            'forecast' => [
                'id' =>
                    $feedback
                        ->forecast
                        ->id,

                'forecast_code' =>
                    $feedback
                        ->forecast
                        ->forecast_code,

                'commodity' => [
                    'id' =>
                        $feedback
                            ->forecast
                            ->commodity
                            ->id,

                    'name' =>
                        $feedback
                            ->forecast
                            ->commodity
                            ->name,
                ],

                'sppg' => [
                    'id' =>
                        $feedback
                            ->forecast
                            ->sppgOrganization
                            ->id,

                    'code' =>
                        $feedback
                            ->forecast
                            ->sppgOrganization
                            ->code,

                    'name' =>
                        $feedback
                            ->forecast
                            ->sppgOrganization
                            ->name,
                ],

                'required_start_at' =>
                    $feedback
                        ->forecast
                        ->required_start_at
                        ?->toIso8601String(),

                'required_end_at' =>
                    $feedback
                        ->forecast
                        ->required_end_at
                        ?->toIso8601String(),
            ],

            'unit' => [
                'id' =>
                    $feedback
                        ->unit
                        ->id,

                'name' =>
                    $feedback
                        ->unit
                        ->name,

                'symbol' =>
                    $feedback
                        ->unit
                        ->symbol,
            ],
        ];
    }
}