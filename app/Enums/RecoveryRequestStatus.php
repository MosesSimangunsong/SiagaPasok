<?php

namespace App\Enums;

enum RecoveryRequestStatus: string
{
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_APPROVAL =>
                'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::APPROVED,
            self::REJECTED => true,

            default => false,
        };
    }
}