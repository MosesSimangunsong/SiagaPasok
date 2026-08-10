<?php

namespace App\Enums;

enum ForecastStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Dipublikasikan',
            self::CLOSED => 'Ditutup',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::CLOSED,
            self::CANCELLED => true,

            default => false,
        };
    }
}