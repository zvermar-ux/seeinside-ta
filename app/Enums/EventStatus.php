<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EventStatus: string implements HasLabel, HasColor
{
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Scheduled => 'Scheduled',
            self::Postponed => 'Postponed',
            self::SoldOut => 'Sold out',
            self::Cancelled => 'Cancelled',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'success',
            self::Postponed => 'warning',
            self::SoldOut => 'danger',
            self::Cancelled => 'danger',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Scheduled => 'Закажан',
            self::Postponed => 'Одложен',
            self::SoldOut => 'Распродаден',
            self::Cancelled => 'Откажан',
        };
        $en = match ($this) {
            self::Scheduled => 'Scheduled',
            self::Postponed => 'Postponed',
            self::SoldOut => 'Sold out',
            self::Cancelled => 'Cancelled',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
