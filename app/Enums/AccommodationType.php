<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccommodationType: string implements HasLabel, HasColor
{
    case Hotel = 'hotel';
    case Apartment = 'apartment';
    case Aparthotel = 'aparthotel';
    case Villa = 'villa';
    case Studio = 'studio';
    case Guesthouse = 'guesthouse';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Hotel => 'Hotel',
            self::Apartment => 'Apartment',
            self::Aparthotel => 'Aparthotel',
            self::Villa => 'Villa',
            self::Studio => 'Studio',
            self::Guesthouse => 'Guesthouse',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hotel => 'success',
            self::Apartment => 'warning',
            self::Aparthotel => 'info',
            self::Villa => 'primary',
            self::Studio => 'gray',
            self::Guesthouse => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Hotel => 'Хотел',
            self::Apartment => 'Апартман',
            self::Aparthotel => 'Апарт-хотел',
            self::Villa => 'Вила',
            self::Studio => 'Студио',
            self::Guesthouse => 'Пансион',
        };
        $en = match ($this) {
            self::Hotel => 'Hotel',
            self::Apartment => 'Apartment',
            self::Aparthotel => 'Aparthotel',
            self::Villa => 'Villa',
            self::Studio => 'Studio',
            self::Guesthouse => 'Guesthouse',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
