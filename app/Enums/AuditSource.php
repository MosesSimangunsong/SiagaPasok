<?php

namespace App\Enums;

enum AuditSource: string
{
    case USER = 'USER';
    case SYSTEM = 'SYSTEM';
}