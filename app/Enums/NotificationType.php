<?php

namespace App\Enums;

enum NotificationType: string
{
    case APPROVAL_REQUIRED =
        'APPROVAL_REQUIRED';

    case SUPPLY_RISK =
        'SUPPLY_RISK';

    case STALE_COMMITMENT =
        'STALE_COMMITMENT';

    case SHORTFALL =
        'SHORTFALL';

    case FALLBACK_REQUEST =
        'FALLBACK_REQUEST';

    case FALLBACK_OFFER_DECISION =
        'FALLBACK_OFFER_DECISION';

    case READINESS =
        'READINESS';

    case RFP =
        'RFP';

    public function label(): string
    {
        return match ($this) {
            self::APPROVAL_REQUIRED =>
                'Persetujuan Diperlukan',

            self::SUPPLY_RISK =>
                'Risiko Pasokan',

            self::STALE_COMMITMENT =>
                'Komitmen Perlu Verifikasi',

            self::SHORTFALL =>
                'Shortfall Pasokan',

            self::FALLBACK_REQUEST =>
                'Fallback Request',

            self::FALLBACK_OFFER_DECISION =>
                'Keputusan Fallback Offer',

            self::READINESS =>
                'Kesiapan Contributor',

            self::RFP =>
                'Ready for Procurement',
        };
    }
}