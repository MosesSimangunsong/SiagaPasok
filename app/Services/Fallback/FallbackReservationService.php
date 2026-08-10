<?php

namespace App\Services\Fallback;

use App\Models\FallbackOffer;
use App\Models\FallbackOfferSource;
use App\Models\SupplyCommitment;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class FallbackReservationService
{
    public function releaseOpenReserve(
        FallbackOffer $offer,
        CarbonInterface $releasedAt,
    ): FixedScaleDecimal {
        $releaseTime =
            CarbonImmutable::instance(
                $releasedAt
            );

        /*
         * Lock ledger dalam ordering yang sama
         * seperti approval/accept path.
         */
        $sourceRows =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $offer->id
                )
                ->orderBy(
                    'supply_commitment_id'
                )
                ->lockForUpdate()
                ->get();

        $commitmentIds =
            $sourceRows
                ->pluck(
                    'supply_commitment_id'
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values()
                ->all();

        if ($commitmentIds !== []) {
            $commitments =
                SupplyCommitment::query()
                    ->whereIn(
                        'id',
                        $commitmentIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

            /*
             * Defensive integrity.
             * FK seharusnya membuat ini mustahil,
             * tetapi capacity mutation harus
             * fail closed pada corrupted state.
             */
            if (
                $commitments->count()
                !== count($commitmentIds)
            ) {
                throw ValidationException::withMessages([
                    'source_ledger' => (
                        'Source Commitment Fallback '
                        .'tidak lengkap.'
                    ),
                ]);
            }
        }

        $totalReleased =
            FixedScaleDecimal::zero();

        foreach ($sourceRows as $sourceRow) {
            $reserved =
                FixedScaleDecimal::from(
                    (string)
                    $sourceRow->reserved_volume
                );

            $allocated =
                FixedScaleDecimal::from(
                    (string)
                    $sourceRow->allocated_volume
                );

            $released =
                FixedScaleDecimal::from(
                    (string)
                    $sourceRow->released_volume
                );

            /*
             * Lifetime ledger invariant:
             *
             * allocated + released <= reserved
             */
            if (
                $allocated
                    ->add($released)
                    ->compare($reserved)
                > 0
            ) {
                throw ValidationException::withMessages([
                    'source_ledger' => (
                        'Fallback Offer memiliki source '
                        .'ledger yang tidak valid.'
                    ),
                ]);
            }

            $consumed =
                $allocated->add(
                    $released
                );

            $openReserve =
                $reserved
                    ->subtractToZero(
                        $consumed
                    );

            if ($openReserve->isZero()) {
                continue;
            }

            $sourceRow->update([
                'released_volume' =>
                    $released
                        ->add($openReserve)
                        ->toString(),

                'released_at' =>
                    $releaseTime,
            ]);

            $totalReleased =
                $totalReleased->add(
                    $openReserve
                );
        }

        return $totalReleased;
    }
}