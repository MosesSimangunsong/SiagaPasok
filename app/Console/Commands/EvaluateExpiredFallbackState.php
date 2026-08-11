<?php

namespace App\Console\Commands;

use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EvaluateExpiredFallbackState extends Command
{
    protected $signature =
        'fallback:evaluate-expiry';

    protected $description =
        'Expire due Fallback Offers and Fallback Requests.';

    public function handle(
        FallbackOfferService $offerService,
        FallbackRequestService $requestService,
    ): int {
        /*
         * Satu immutable evaluation instant dipakai
         * untuk seluruh lifecycle evaluation pada run
         * yang sama.
         */
        $evaluationTime =
            CarbonImmutable::now();

        $expiredOffers = 0;
        $expiredRequests = 0;
        $failed = false;

        /*
         * Offer lebih dahulu.
         *
         * Offer dapat memiliki expiry lebih awal daripada
         * response deadline Request.
         *
         * Pada boundary:
         *
         * T >= offer.expires_at
         *     -> Offer dapat EXPIRED.
         *
         * T == request.response_deadline_at
         *     -> Request masih OPEN.
         */
        try {
            $expiredOffers =
                $offerService
                    ->expireDueAvailableOffers(
                        $evaluationTime
                    );
        } catch (Throwable $exception) {
            $failed = true;

            report(
                $exception
            );

            $this->error(
                'Fallback Offer expiry gagal: '
                .$exception->getMessage()
            );
        }

        /*
         * Request expiry memakai service canonical.
         *
         * Service juga bertanggung jawab terhadap
         * cleanup AVAILABLE Offer yang masih tersisa
         * ketika Request benar-benar melewati deadline.
         */
        try {
            $expiredRequests =
                $requestService
                    ->expireDueOpenRequests(
                        $evaluationTime
                    );
        } catch (Throwable $exception) {
            $failed = true;

            report(
                $exception
            );

            $this->error(
                'Fallback Request expiry gagal: '
                .$exception->getMessage()
            );
        }

        $this->info(
            'Evaluation time: '
            .$evaluationTime
                ->toIso8601String()
            .'; expired offers: '
            .$expiredOffers
            .'; expired requests: '
            .$expiredRequests
            .'.'
        );

        return $failed
            ? self::FAILURE
            : self::SUCCESS;
    }
}