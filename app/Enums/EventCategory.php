<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EventCategory: string implements HasLabel, HasColor
{
    case Concert = 'concert';
    case Sport = 'sport';
    case Theatre = 'theatre';
    case Festival = 'festival';
    case Exhibition = 'exhibition';
    case Conference = 'conference';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Concert => 'Concert',
            self::Sport => 'Sport event',
            self::Theatre => 'Theatre',
            self::Festival => 'Festival',
            self::Exhibition => 'Exhibition / fair',
            self::Conference => 'Conference',
            self::Other => 'Other',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Concert => 'primary',
            self::Sport => 'success',
            self::Theatre => 'info',
            self::Festival => 'warning',
            self::Exhibition => 'gray',
            self::Conference => 'gray',
            self::Other => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Concert => 'Концерт',
            self::Sport => 'Спортски настан',
            self::Theatre => 'Театар',
            self::Festival => 'Фестивал',
            self::Exhibition => 'Изложба / саем',
            self::Conference => 'Конференција',
            self::Other => 'Друго',
        };
        $en = match ($this) {
            self::Concert => 'Concert',
            self::Sport => 'Sport event',
            self::Theatre => 'Theatre',
            self::Festival => 'Festival',
            self::Exhibition => 'Exhibition / fair',
            self::Conference => 'Conference',
            self::Other => 'Other',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
