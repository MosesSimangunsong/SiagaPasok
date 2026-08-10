<?php

namespace App\Enums;

enum RequirementScope: string
{
    case ORGANIZATION = 'ORGANIZATION';
    case FORECAST = 'FORECAST';
}