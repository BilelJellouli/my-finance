<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Weekly',
            self::BIWEEKLY => 'Every two weeks',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }
}
