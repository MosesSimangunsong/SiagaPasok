<?php

namespace App\Enums;

enum CommitmentLifecycleStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::CANCELLED => 'Dibatalkan',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::CANCELLED,
            self::EXPIRED => true,

            default => false,
        };
    }
}