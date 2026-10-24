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
            'price_lines' => 'array',
            'discount_percent' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'option_until' => 'date',
            'payment_due' => 'date',
            'cancel_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $b) {
            $b->reference ??= 'R'.now()->format('ym').'-'.Str::upper(Str::random(5));
            $b->access_token ??= Str::random(40);
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

        // every passenger ends up in the client list (and the holder is linked)
        static::saved(function (Booking $b) {
            if (! $b->wasChanged('passengers') && ! $b->wasRecentlyCreated) {
                return;
            }
            $rows = (array) $b->passengers;
            $changed = false;
            foreach ($rows as $k => $p) {
                if (! is_array($p)) {
                    continue;
                }
                try {
                    $c = Client::fromPassenger($p, (int) $b->agency_id);
                } catch (\Throwable $e) {
                    report($e);
                    $c = null;
                }
                if ($c && (int) ($p['client_id'] ?? 0) !== $c->id) {
                    $rows[$k]['client_id'] = $c->id;
                    $changed = true;
                }
            }
            $first = collect($rows)->first(fn ($p) => ! empty($p['client_id']));
            $holder = $b->client_id ?: ($first['client_id'] ?? null);
            if ($changed || $holder !== $b->client_id) {
                $b->passengers = $rows;
                $b->client_id = $holder;
                $b->saveQuietly();
            }
        });

        // automatic messages to the client when the agency confirms / cancels
        static::updated(function (Booking $b) {
            if (! $b->wasChanged('status')) {
                return;
            }
            $event = match ($b->status) {
                BookingStatus::Confirmed => 'booking_confirmed',
                BookingStatus::Cancelled => 'booking_cancelled',
                default => null,
            };
            if ($event) {
                $fresh = $b->fresh();
                \App\Services\Messaging\Messenger::later(fn () => \App\Services\Messaging\Messenger::notify($event, $fresh, once: true));
            }
        });

        static::saving(function (Booking $b) {
            if (! $b->exchange_rate && $b->currency && $b->currency !== 'MKD' && ($a = Agency::find($b->agency_id))) {
                $b->exchange_rate = $a->eurRate();
            }
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'company_id');
    }

    public function subagent(): BelongsTo
    {
        return $this->belongsTo(Subagent::class);
    }

    public function stayTerm(): BelongsTo
    {
        return $this->belongsTo(StayTerm::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }

    /** Amount in MKD (or the agency's base currency) */
    public function inBase(float $amount): float
    {
        $a = $this->agency ?? Agency::find($this->agency_id);

        return $a ? $a->toBase($amount, $this->currency, $this->exchange_rate ? (float) $this->exchange_rate : null) : $amount;
    }

    /** An amount in another currency (e.g. a supplier invoice in MKD) expressed in the booking currency */
    public function fromCurrency(float $amount, ?string $currency, ?float $rate = null): float
    {
        if (! $currency || $currency === $this->currency) {
            return $amount;
        }
        $a = $this->agency ?? Agency::find($this->agency_id);
        if (! $a) {
            return $amount;
        }
        $base = $a->toBase($amount, $currency, $rate ?: null);
        if ($this->currency === $a->doc('base_currency', 'MKD')) {
            return round($base, 2);
        }
        $r = (float) ($this->exchange_rate ?: $a->eurRate());

        return $r > 0 ? round($base / $r, 2) : $amount;
    }

    /** Passenger rows with names (for documents) */
    public function passengerList(): array
    {
        $rows = array_values(array_filter((array) $this->passengers, fn ($p) => is_array($p) && trim(($p['first_name'] ?? '').($p['last_name'] ?? '')) !== ''));
        if (! $rows && $this->customer_name) {
            $rows[] = ['first_name' => $this->customer_name, 'last_name' => '', 'phone' => $this->customer_phone];
        }

        return $rows;
    }

    /**
     * Calculation lines: [kind (base|supplement|discount), label, qty, price, total].
     * Saved lines win; otherwise they are built from the offer (departure prices, extras, room, ticket).
     */
    public function priceLines(): array
    {
        $lines = array_values(array_filter((array) $this->price_lines, fn ($l) => is_array($l) && filled($l['label'] ?? null)));
        if ($lines) {
            return array_map(fn ($l) => $l + ['kind' => 'base', 'qty' => 1, 'price' => 0, 'total' => round((float) ($l['qty'] ?? 1) * (float) ($l['price'] ?? 0), 2)], $lines);
        }

        return \App\Services\Bookings\BookingPricing::suggest($this);
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
