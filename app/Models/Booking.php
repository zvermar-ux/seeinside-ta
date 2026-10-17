<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'children_ages' => 'array',
            'passengers' => 'array',
            'extras' => 'array',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $b) {
            $b->reference ??= 'R'.now()->format('ym').'-'.Str::upper(Str::random(5));
        });

        static::saving(function (Booking $b) {
            if ($b->status === BookingStatus::Confirmed && ! $b->confirmed_at) {
                $b->confirmed_at = now();
            }
        });
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function departurePlace(): BelongsTo
    {
        return $this->belongsTo(DeparturePlace::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoom::class, 'accommodation_room_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EventTicket::class, 'event_ticket_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function itemTitle(): string
    {
        $item = $this->bookable;
        if (! $item) {
            return '—';
        }

        return method_exists($item, 'tr') && is_array($item->getAttribute('title'))
            ? (string) $item->tr('title')
            : (string) ($item->name ?? '');
    }
}
