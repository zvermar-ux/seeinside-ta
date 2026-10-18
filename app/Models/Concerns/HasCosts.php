<?php

namespace App\Models\Concerns;

use App\Models\Booking;
use App\Models\OfferCost;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Offers that carry agency costs and receive bookings (tours, events, hotels, charters, visas) */
trait HasCosts
{
    public function costs(): MorphMany
    {
        return $this->morphMany(OfferCost::class, 'costable')->orderBy('sort_order')->orderBy('id');
    }

    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }
}
