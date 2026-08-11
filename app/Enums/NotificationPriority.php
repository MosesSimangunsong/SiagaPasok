<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case ACTION =
        'ACTION';

    case WARNING =
        'WARNING';

    case INFORMATION =
        'INFORMATION';

    public function label(): string
    {
        return match ($this) {
            self::ACTION =>
                'Tindakan',

            self::WARNING =>
                'Peringatan',

            self::INFORMATION =>
                'Informasi',
        };
    }
}