<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermPrice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'stop_sale' => 'boolean'];
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
