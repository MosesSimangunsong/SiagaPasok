<?php

namespace App\Enums;

enum FallbackOfferStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case AVAILABLE = 'AVAILABLE';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT =>
                'Draft',

            self::PENDING_APPROVAL =>
                'Menunggu Persetujuan',

            self::AVAILABLE =>
                'Tersedia',

            self::ACCEPTED =>
                'Diterima',

            self::REJECTED =>
                'Ditolak',

            self::WITHDRAWN =>
                'Ditarik',

            self::EXPIRED =>
                'Kedaluwarsa',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isAccepted(): bool
    {
        return $this === self::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isWithdrawn(): bool
    {
        return $this === self::WITHDRAWN;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::ACCEPTED,
            self::REJECTED,
            self::WITHDRAWN,
            self::EXPIRED => true,

            default => false,
        };
    }
}