<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TourType: string implements HasLabel, HasColor
{
    case Bus = 'bus';
    case Flight = 'flight';
    case Cruise = 'cruise';
    case CityBreak = 'city_break';
    case Excursion = 'excursion';
    case Ski = 'ski';
    case Wellness = 'wellness';
    case Pilgrimage = 'pilgrimage';
    case Exotic = 'exotic';
    case Holiday = 'holiday';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Bus => 'Bus tour',
            self::Flight => 'Flight tour',
            self::Cruise => 'Cruise',
            self::CityBreak => 'City break',
            self::Excursion => 'Excursion',
            self::Ski => 'Ski',
            self::Wellness => 'Wellness & spa',
            self::Pilgrimage => 'Pilgrimage',
            self::Exotic => 'Exotic',
            self::Holiday => 'Holiday package',
            self::Other => 'Other',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bus => 'info',
            self::Flight => 'primary',
            self::Cruise => 'info',
            self::CityBreak => 'success',
            self::Excursion => 'success',
            self::Ski => 'gray',
            self::Wellness => 'warning',
            self::Pilgrimage => 'gray',
            self::Exotic => 'warning',
            self::Holiday => 'success',
            self::Other => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Bus => 'Автобуска тура',
            self::Flight => 'Авионска тура',
            self::Cruise => 'Крстарење',
            self::CityBreak => 'Викенд во град',
            self::Excursion => 'Екскурзија',
            self::Ski => 'Скијање',
            self::Wellness => 'Велнес и спа',
            self::Pilgrimage => 'Аџилак',
            self::Exotic => 'Егзотика',
            self::Holiday => 'Летување',
            self::Other => 'Друго',
        };
        $en = match ($this) {
            self::Bus => 'Bus tour',
            self::Flight => 'Flight tour',
            self::Cruise => 'Cruise',
            self::CityBreak => 'City break',
            self::Excursion => 'Excursion',
            self::Ski => 'Ski',
            self::Wellness => 'Wellness & spa',
            self::Pilgrimage => 'Pilgrimage',
            self::Exotic => 'Exotic',
            self::Holiday => 'Holiday package',
            self::Other => 'Other',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
