<?php

namespace App\Enums;

enum OrganizationType: string
{
    case SPPG = 'SPPG';
    case KDKMP = 'KDKMP';

    public function label(): string
    {
        return match ($this) {
            self::SPPG => 'SPPG',
            self::KDKMP => 'KDKMP / Koperasi',
        };
    }
}