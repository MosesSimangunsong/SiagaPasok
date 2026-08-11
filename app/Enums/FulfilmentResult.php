<?php

namespace App\Enums;

enum FulfilmentResult: string
{
    case FULFILLED = 'FULFILLED';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::FULFILLED =>
                'Terpenuhi',

            self::PARTIAL =>
                'Terpenuhi Sebagian',

            self::FAILED =>
                'Tidak Terpenuhi',
        };
    }
}