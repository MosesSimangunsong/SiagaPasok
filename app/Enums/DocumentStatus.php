<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'PENDING';
    case VALID = 'VALID';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';
}