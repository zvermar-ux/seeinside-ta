<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        // an amount entered when the booking is created becomes its first payment
        static::created(function (Booking $b) {
            if ((float) $b->paid_amount > 0) {
                BookingPayment::create([
                    'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'amount' => $b->paid_amount, 'paid_at' => now()->toDateString(),
                    'method' => array_key_exists((string) $b->payment_method, BookingPayment::methods()) ? $b->payment_method : null,
                    'note' => $b->payment_method && ! array_key_exists((string) $b->payment_method, BookingPayment::methods()) ? $b->payment_method : null,
                ]);
            }
        });

        static::saving(function (Booking $b) {
            if ($b->exists && $b->isDirty('total') && $b->payment_status !== PaymentStatus::Refunded && $b->payments()->exists()) {
                $b->payment_status = self::paymentStatusFor((float) $b->paid_amount, $b->total);
            }
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

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    /** paid_amount and payment status follow the recorded payments */
    public function syncPaid(): void
    {
        $paid = round((float) $this->payments()->withoutGlobalScopes()->sum('amount'), 2);
        $this->paid_amount = $paid;
        if ($this->payment_status !== PaymentStatus::Refunded || $paid > 0) {
            $this->payment_status = self::paymentStatusFor($paid, $this->total);
        }
        $this->saveQuietly();
    }

    public static function paymentStatusFor(float $paid, $total): PaymentStatus
    {
        $total = (float) $total;
        if ($paid <= 0) {
            return PaymentStatus::Unpaid;
        }

        return $total > 0 && $paid + 0.009 < $total ? PaymentStatus::Deposit : PaymentStatus::Paid;
    }

    /** What the client still owes (never negative) */
    public function balance(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    public function passengerCount(): int
    {
        return max(1, (int) $this->adults + (int) $this->children);
    }

    public function nights(): int
    {
        if ($this->date_from && $this->date_to) {
            return max(0, (int) $this->date_from->diffInDays($this->date_to));
        }

        return 0;
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
