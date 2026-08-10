<?php

namespace App\Enums;

enum NetworkRole: string
{
    case PRIMARY = 'PRIMARY';
    case NETWORK = 'NETWORK';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARY => 'KDKMP Utama',
            self::NETWORK => 'KDKMP Jaringan',
        };
    }

    public function isPrimary(): bool
    {
        return $this === self::PRIMARY;
    }

    public function isNetwork(): bool
    {
        return $this === self::NETWORK;
    }
}