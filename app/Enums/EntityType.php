<?php

namespace App\Enums;

enum EntityType: string
{
    case PERSONAL = 'personal';
    case LLC = 'llc';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Personal',
            self::LLC => 'LLC',
        };
    }
}
