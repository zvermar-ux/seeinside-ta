<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GroupType: string implements HasLabel
{
    case Group = 'group';
    case Destination = 'destination';
    case Place = 'place';

    public function getLabel(): string
    {
        return match ($this) {
            self::Group => __('Group'),
            self::Destination => __('Destination'),
            self::Place => __('Place'),
        };
    }
}
