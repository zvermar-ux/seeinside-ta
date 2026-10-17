<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisaCategory: string implements HasLabel, HasColor
{
    case Tourist = 'tourist';
    case Business = 'business';
    case Student = 'student';
    case Work = 'work';
    case Transit = 'transit';
    case Evisa = 'evisa';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Tourist => 'Tourist',
            self::Business => 'Business',
            self::Student => 'Student',
            self::Work => 'Work',
            self::Transit => 'Transit',
            self::Evisa => 'e-Visa',
            self::Other => 'Other',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tourist => 'info',
            self::Business => 'primary',
            self::Student => 'success',
            self::Work => 'warning',
            self::Transit => 'gray',
            self::Evisa => 'info',
            self::Other => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Tourist => 'Туристичка',
            self::Business => 'Бизнис',
            self::Student => 'Студентска',
            self::Work => 'Работна',
            self::Transit => 'Транзитна',
            self::Evisa => 'Електронска виза',
            self::Other => 'Друго',
        };
        $en = match ($this) {
            self::Tourist => 'Tourist',
            self::Business => 'Business',
            self::Student => 'Student',
            self::Work => 'Work',
            self::Transit => 'Transit',
            self::Evisa => 'e-Visa',
            self::Other => 'Other',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
