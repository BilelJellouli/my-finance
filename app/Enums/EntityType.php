<?php

namespace App\Enums;

enum EntityType: string
{
    case Personal = 'personal';
    case Llc = 'llc';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Llc => 'LLC',
        };
    }
}
