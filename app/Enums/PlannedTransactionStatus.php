<?php

namespace App\Enums;

enum PlannedTransactionStatus: string
{
    case PLANNED = 'planned';
    case SETTLED = 'settled';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::SETTLED => 'Settled',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
        };
    }
}
