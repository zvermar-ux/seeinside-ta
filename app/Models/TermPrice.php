<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermPrice extends Model
{
    protected $guarded = ['id'];

    /** "2+1" → [2, 1] */
    public static function occ(string $key): array
    {
        $p = array_map('intval', explode('+', $key, 2));

        return [$p[0] ?? 0, $p[1] ?? 0];
    }

    /** "2" / "2+1" for these guests */
    public static function occKey(int $adults, int $children): string
    {
        return $adults.($children ? '+'.$children : '');
    }

    /**
     * The price for these guests: the price of the room, or – when the price list has prices by the people in the room
     * (2 adults 599, 2+2 769…) – the exact one, otherwise the cheapest that takes them; null = these guests are not offered
     */
    public function priceFor(int $adults, int $children): ?float
    {
        $occ = array_filter((array) $this->occupancy_prices, fn ($v) => $v !== null && $v !== '');
        if (! $occ) {
            return $this->price !== null ? (float) $this->price : null;
        }
        $key = self::occKey($adults, $children);
        if (isset($occ[$key])) {
            return (float) $occ[$key];
        }
        $fit = [];
        foreach ($occ as $k => $v) {
            [$a, $c] = self::occ((string) $k);
            if ($a >= $adults && $a + $c >= $adults + $children) {
                $fit[] = (float) $v;
            }
        }

        return $fit ? min($fit) : null;
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'stop_sale' => 'boolean', 'price_manual' => 'boolean', 'occupancy_prices' => 'array', 'supplier_price' => 'decimal:2', 'supplier_synced_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // the "price from" of the accommodation follows the term prices
        static::saved(fn (TermPrice $p) => static::$quiet || $p->term?->accommodation?->touch());
    }

    /** set during bulk saves (price matrix) – the accommodation is touched once at the end */
    public static bool $quiet = false;

    public function term(): BelongsTo
    {
        return $this->belongsTo(StayTerm::class, 'stay_term_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoom::class, 'accommodation_room_id');
    }
}
