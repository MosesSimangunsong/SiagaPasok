<?php

namespace App\Enums;

enum UserRole: string
{
    case SYSTEM_ADMIN = 'SYSTEM_ADMIN';
    case SPPG_USER = 'SPPG_USER';
    case KDKMP_OPERATOR = 'KDKMP_OPERATOR';
    case KDKMP_MANAGER = 'KDKMP_MANAGER';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_ADMIN => 'System Admin',
            self::SPPG_USER => 'SPPG User',
            self::KDKMP_OPERATOR => 'KDKMP Operator / FRPL',
            self::KDKMP_MANAGER => 'KDKMP Manager',
        };
    }

    public function requiresOrganization(): bool
    {
        return $this !== self::SYSTEM_ADMIN;
    }

    public function isKdkmpRole(): bool
    {
        return in_array($this, [
            self::KDKMP_OPERATOR,
            self::KDKMP_MANAGER,
        ], true);
    }
}