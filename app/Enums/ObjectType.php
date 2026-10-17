<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ObjectType: string implements HasLabel, HasColor, HasIcon
{
    case Arrangement = 'arrangement';
    case Hotel = 'hotel';
    case Apartment = 'apartment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Arrangement => __('Arrangement'),
            self::Hotel => __('Hotel'),
            self::Apartment => __('Apartment'),
        };
    }

    public function getPluralLabel(): string
    {
        return match ($this) {
            self::Arrangement => __('Arrangements'),
            self::Hotel => __('Hotels'),
            self::Apartment => __('Apartments'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Arrangement => 'info',
            self::Hotel => 'success',
            self::Apartment => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Arrangement => 'heroicon-o-paper-airplane',
            self::Hotel => 'heroicon-o-building-office-2',
            self::Apartment => 'heroicon-o-home-modern',
        };
    }

    /** Aries "vid" code */
    public static function fromAries(int|string $vid): self
    {
        return match ((int) $vid) {
            1 => self::Hotel,
            2 => self::Apartment,
            default => self::Arrangement,
        };
    }
}
