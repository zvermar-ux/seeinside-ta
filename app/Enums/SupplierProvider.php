<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SupplierProvider: string implements HasLabel, HasColor
{
    case Expert = 'expert';
    case Hotelbeds = 'hotelbeds';
    case Filos = 'filos';
    case Bros = 'bros';
    case Seayou = 'seayou';
    case Potw = 'potw';
    case Golibe = 'golibe';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Expert => 'Expert (availability)',
            self::Hotelbeds => 'Hotelbeds',
            self::Filos => 'Filos Travel',
            self::Bros => 'Bros Travel',
            self::Seayou => 'Seayou',
            self::Potw => 'People of the World',
            self::Golibe => 'GOL IBE (flights)',
            self::Other => 'Other',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Expert => 'primary',
            self::Hotelbeds => 'info',
            self::Filos => 'info',
            self::Bros => 'info',
            self::Seayou => 'info',
            self::Potw => 'info',
            self::Golibe => 'warning',
            self::Other => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Expert => 'Expert (достапност)',
            self::Hotelbeds => 'Hotelbeds',
            self::Filos => 'Filos Travel',
            self::Bros => 'Bros Travel',
            self::Seayou => 'Seayou',
            self::Potw => 'People of the World',
            self::Golibe => 'GOL IBE (авио)',
            self::Other => 'Друго',
        };
        $en = match ($this) {
            self::Expert => 'Expert (availability)',
            self::Hotelbeds => 'Hotelbeds',
            self::Filos => 'Filos Travel',
            self::Bros => 'Bros Travel',
            self::Seayou => 'Seayou',
            self::Potw => 'People of the World',
            self::Golibe => 'GOL IBE (flights)',
            self::Other => 'Other',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
