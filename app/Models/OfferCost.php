<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One cost line (rate) of an offer: what the agency pays to run it */
class OfferCost extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'quantity' => 'decimal:2', 'paid_to_supplier' => 'decimal:2',
            'valid_from' => 'date', 'valid_to' => 'date', 'due_date' => 'date',
        ];
    }

    public static function categories(): array
    {
        return [
            'transport' => __('Transport'), 'accommodation' => __('Accommodation'), 'guide' => __('Guide / escort'),
            'tickets' => __('Tickets & entrances'), 'food' => __('Food'), 'insurance' => __('Insurance'), 'visa' => __('Visas & fees'),
            'lease' => __('Lease / rent'), 'maintenance' => __('Maintenance'), 'utilities' => __('Utilities (power, water, internet)'), 'cleaning' => __('Cleaning & laundry'),
            'staff' => __('Staff'), 'taxes' => __('Taxes & fees'),
            'commission' => __('Commissions'), 'marketing' => __('Marketing'), 'other' => __('Other'),
        ];
    }

    /** How the line is multiplied */
    public static function bases(): array
    {
        return [
            'fixed' => __('Fixed amount (once)'),
            'per_departure' => __('Per departure / date with bookings'),
            'per_booking' => __('Per booking'),
            'per_person' => __('Per passenger'),
            'per_adult' => __('Per adult'),
            'per_child' => __('Per child'),
            'per_person_night' => __('Per passenger per night'),
            'per_room' => __('Per room / unit'),
            'per_room_night' => __('Per room per night'),
            'percent_revenue' => __('% of sales'),
        ];
    }

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoom::class, 'accommodation_room_id');
    }

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class);
    }
}
