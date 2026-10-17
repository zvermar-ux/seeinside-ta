<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DepartureStatus: string implements HasLabel, HasColor
{
    case Available = 'available';
    case Limited = 'limited';
    case OnRequest = 'on_request';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Available => 'Available',
            self::Limited => 'Few seats left',
            self::OnRequest => 'On request',
            self::SoldOut => 'Sold out',
            self::Cancelled => 'Cancelled',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Limited => 'warning',
            self::OnRequest => 'info',
            self::SoldOut => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Available => 'Слободно',
            self::Limited => 'Малку места',
            self::OnRequest => 'На барање',
            self::SoldOut => 'Распродадено',
            self::Cancelled => 'Откажано',
        };
        $en = match ($this) {
            self::Available => 'Available',
            self::Limited => 'Few seats left',
            self::OnRequest => 'On request',
            self::SoldOut => 'Sold out',
            self::Cancelled => 'Cancelled',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
