<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RoomType: string implements HasLabel, HasColor
{
    case Single = 'single';
    case Double = 'double';
    case Twin = 'twin';
    case Triple = 'triple';
    case Quad = 'quad';
    case Family = 'family';
    case Studio = 'studio';
    case Apartment = 'apartment';
    case Suite = 'suite';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Single => 'Single room',
            self::Double => 'Double room',
            self::Twin => 'Twin room',
            self::Triple => 'Triple room',
            self::Quad => 'Quadruple room',
            self::Family => 'Family room',
            self::Studio => 'Studio',
            self::Apartment => 'Apartment',
            self::Suite => 'Suite',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'gray',
            self::Double => 'gray',
            self::Twin => 'gray',
            self::Triple => 'gray',
            self::Quad => 'gray',
            self::Family => 'gray',
            self::Studio => 'gray',
            self::Apartment => 'gray',
            self::Suite => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Single => 'Еднокреветна соба',
            self::Double => 'Двокреветна соба',
            self::Twin => 'Соба со два одделни кревети',
            self::Triple => 'Трокреветна соба',
            self::Quad => 'Четирикреветна соба',
            self::Family => 'Семејна соба',
            self::Studio => 'Студио',
            self::Apartment => 'Апартман',
            self::Suite => 'Апартман/свит',
        };
        $en = match ($this) {
            self::Single => 'Single room',
            self::Double => 'Double room',
            self::Twin => 'Twin room',
            self::Triple => 'Triple room',
            self::Quad => 'Quadruple room',
            self::Family => 'Family room',
            self::Studio => 'Studio',
            self::Apartment => 'Apartment',
            self::Suite => 'Suite',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
