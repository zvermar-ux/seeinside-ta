<?php

namespace App\Models;

use App\Enums\DepartureStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDeparture extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => DepartureStatus::class,
            'is_last_minute' => 'boolean',
            'price_adult' => 'decimal:2',
            'price_child' => 'decimal:2',
            'last_minute_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TourDeparture $d) {
            $d->is_last_minute = (bool) $d->is_last_minute;
            $d->seats_booked = (int) $d->seats_booked;
            $d->status ??= \App\Enums\DepartureStatus::Available;
        });

        // keep the tour's "price from" current
        static::saved(fn (TourDeparture $d) => $d->tour?->save());
        static::deleted(fn (TourDeparture $d) => $d->tour?->save());
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function seatsLeft(): ?int
    {
        return $this->seats_total === null ? null : max(0, $this->seats_total - $this->seats_booked);
    }

    public function adultPrice(): ?float
    {
        if ($this->is_last_minute && $this->last_minute_price !== null) {
            return (float) $this->last_minute_price;
        }
        $p = $this->price_adult ?? $this->tour?->price_adult;

        return $p === null ? null : $this->tour?->promoPrice((float) $p);
    }

    public function childPrice(): ?float
    {
        $p = $this->price_child ?? $this->tour?->price_child;

        return $p === null ? null : (float) $p;
    }
}
