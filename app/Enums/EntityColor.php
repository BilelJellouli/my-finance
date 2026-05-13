<?php

namespace App\Enums;

enum EntityColor: string
{
    case GREEN = 'green';
    case BLUE = 'blue';
    case PURPLE = 'purple';
    case PINK = 'pink';
    case RED = 'red';
    case ORANGE = 'orange';
    case AMBER = 'amber';
    case TEAL = 'teal';
    case CYAN = 'cyan';
    case SLATE = 'slate';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
