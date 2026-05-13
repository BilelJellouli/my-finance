<?php

namespace App\Enums;

enum EntityColor: string
{
    case Green = 'green';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Slate = 'slate';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
