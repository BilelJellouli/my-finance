<?php

namespace App\Enums;

enum Currency: string
{
    case TND = 'TND';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case CHF = 'CHF';
    case CAD = 'CAD';
    case AUD = 'AUD';

    public function label(): string
    {
        return match ($this) {
            self::TND => 'Tunisian Dinar',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
            self::JPY => 'Japanese Yen',
            self::CHF => 'Swiss Franc',
            self::CAD => 'Canadian Dollar',
            self::AUD => 'Australian Dollar',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::TND => 'د.ت',
            self::USD, self::CAD, self::AUD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY => '¥',
            self::CHF => 'CHF',
        };
    }
}
