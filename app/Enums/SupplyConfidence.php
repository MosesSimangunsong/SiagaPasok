<?php

namespace App\Enums;

enum SupplyConfidence: string
{
    case GREEN = 'GREEN';
    case YELLOW = 'YELLOW';
    case RED = 'RED';

    public function label(): string
    {
        return match ($this) {
            self::GREEN => 'Hijau',
            self::YELLOW => 'Kuning',
            self::RED => 'Merah',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::RED;
    }
}