<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Board: string implements HasLabel, HasColor
{
    case Ro = 'RO';
    case Bb = 'BB';
    case Hb = 'HB';
    case Fb = 'FB';
    case Ai = 'AI';
    case Uai = 'UAI';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Ro => 'Room only',
            self::Bb => 'Bed & breakfast',
            self::Hb => 'Half board',
            self::Fb => 'Full board',
            self::Ai => 'All inclusive',
            self::Uai => 'Ultra all inclusive',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ro => 'gray',
            self::Bb => 'gray',
            self::Hb => 'gray',
            self::Fb => 'gray',
            self::Ai => 'gray',
            self::Uai => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Ro => 'Само ноќевање',
            self::Bb => 'Ноќевање со појадок',
            self::Hb => 'Полупансион',
            self::Fb => 'Полн пансион',
            self::Ai => 'All inclusive',
            self::Uai => 'Ultra all inclusive',
        };
        $en = match ($this) {
            self::Ro => 'Room only',
            self::Bb => 'Bed & breakfast',
            self::Hb => 'Half board',
            self::Fb => 'Full board',
            self::Ai => 'All inclusive',
            self::Uai => 'Ultra all inclusive',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}
