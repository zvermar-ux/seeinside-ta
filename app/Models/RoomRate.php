<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'price' => 'decimal:2', 'is_last_minute' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (RoomRate $r) {
            $r->is_last_minute = (bool) $r->is_last_minute;
            $r->min_nights = max(1, (int) $r->min_nights);
            $r->price_type ??= 'per_room';
        });
        static::saved(fn (RoomRate $r) => $r->room?->accommodation?->save());
        static::deleted(fn (RoomRate $r) => $r->room?->accommodation?->save());
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoom::class, 'accommodation_room_id');
    }
}
