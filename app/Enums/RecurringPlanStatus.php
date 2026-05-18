<?php

namespace App\Enums;

enum RecurringPlanStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ENDED = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::ENDED => 'Ended',
        };
    }
}
