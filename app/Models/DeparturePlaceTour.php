<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Departure city of a tour with time and surcharge (pivot as a model, for the repeater) */
class DeparturePlaceTour extends Model
{
    protected $table = 'departure_place_tour';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['surcharge' => 'decimal:2'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function departurePlace(): BelongsTo
    {
        return $this->belongsTo(DeparturePlace::class);
    }
}
