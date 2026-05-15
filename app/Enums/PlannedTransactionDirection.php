<?php

namespace App\Enums;

enum PlannedTransactionDirection: string
{
    case INCOMING = 'incoming';
    case OUTGOING = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::INCOMING => 'Incoming',
            self::OUTGOING => 'Outgoing',
        };
    }
}
