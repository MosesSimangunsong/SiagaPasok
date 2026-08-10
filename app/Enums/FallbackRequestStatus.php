<?php

namespace App\Enums;

enum FallbackRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case OPEN = 'OPEN';
    case REJECTED = 'REJECTED';
    case FULFILLED = 'FULFILLED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT =>
                'Draft',

            self::PENDING_APPROVAL =>
                'Menunggu Persetujuan',

            self::OPEN =>
                'Terbuka',

            self::REJECTED =>
                'Ditolak',

            self::FULFILLED =>
                'Terpenuhi',

            self::EXPIRED =>
                'Kedaluwarsa',

            self::CANCELLED =>
                'Dibatalkan',
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

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isFulfilled(): bool
    {
        return $this === self::FULFILLED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::REJECTED,
            self::FULFILLED,
            self::EXPIRED,
            self::CANCELLED => true,

            default => false,
        };
    }
}