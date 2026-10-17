<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $guarded = ['id'];

    public const DEFAULTS = [
        ['Wizz Air', 'W6', true], ['Ryanair', 'FR', true], ['easyJet', 'U2', true], ['Pegasus Airlines', 'PC', true],
        ['Eurowings', 'EW', true], ['Turkish Airlines', 'TK', false], ['Lufthansa', 'LH', false], ['Austrian Airlines', 'OS', false],
        ['Swiss', 'LX', false], ['Air Serbia', 'JU', false], ['Aegean Airlines', 'A3', false], ['Croatia Airlines', 'OU', false],
        ['Qatar Airways', 'QR', false], ['Emirates', 'EK', false], ['flydubai', 'FZ', true], ['Air Arabia', 'G9', true],
    ];

    protected function casts(): array
    {
        return ['is_low_cost' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as [$name, $iata, $lowCost]) {
            static::firstOrCreate(['iata_code' => $iata], ['name' => $name, 'is_low_cost' => $lowCost]);
        }
    }
}
