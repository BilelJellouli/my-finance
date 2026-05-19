<?php

namespace App\Enums;

enum TransactionKind: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CARD = 'card';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BANK_TRANSFER => 'Bank transfer',
            self::CARD => 'Card',
        };
    }
}
