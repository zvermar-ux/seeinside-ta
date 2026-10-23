<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** A client's payment (instalment) for a booking; negative amount = refund */
class BookingPayment extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'original_amount' => 'decimal:2', 'exchange_rate' => 'decimal:4'];
    }

    public static function methods(): array
    {
        return ['cash' => __('Cash'), 'card' => __('Card'), 'bank' => __('Bank transfer'), 'online' => __('Online payment'), 'other' => __('Other')];
    }

    protected static function booted(): void
    {
        static::creating(function (BookingPayment $p) {
            $p->created_by ??= Auth::id();
            $p->agency_id ??= $p->booking?->agency_id;
            $p->paid_at ??= now();
        });
        // paid in another currency (e.g. MKD for a EUR booking): convert with the agreed rate
        static::saving(function (BookingPayment $p) {
            $b = $p->booking;
            if ($b && $p->currency && $p->currency !== $b->currency && $p->original_amount !== null) {
                $agency = Agency::find($b->agency_id);
                $rate = (float) ($p->exchange_rate ?: $b->exchange_rate ?: $agency?->eurRate() ?: 1);
                $p->exchange_rate = $rate;
                // MKD → EUR: divide; EUR → MKD: multiply
                $p->amount = $p->currency === ($agency?->doc('base_currency') ?? 'MKD')
                    ? round((float) $p->original_amount / $rate, 2)
                    : round((float) $p->original_amount * $rate, 2);
            }
        });
        // cash / card / bank payments get a cash register (or bank) document (Каса прими / Каса исплати) with its own number
        static::created(function (BookingPayment $p) {
            if (static::$withoutCash || ! in_array($p->method, ['cash', 'card', 'bank'], true) || ! $p->booking) {
                return;
            }
            try {
                $b = $p->booking;
                $orig = $p->original_amount !== null ? (float) $p->original_amount : (float) $p->amount;
                CashEntry::create([
                    'register' => static::$register ?: \App\Services\Finance\Finance::registerFor((string) $p->method, Agency::find($p->agency_id)),
                    'agency_id' => $p->agency_id, 'direction' => $orig < 0 ? 'out' : 'in', 'entry_at' => $p->paid_at?->copy()->setTimeFrom(now()) ?? now(),
                    'amount' => abs($orig), 'currency' => $p->currency ?: $b->currency, 'exchange_rate' => $p->exchange_rate ?: $b->exchange_rate,
                    'method' => $p->method, 'category' => $orig < 0 ? 'refund' : 'arrangement',
                    'party_name' => $b->client?->display_name ?: $b->customer_name, 'client_id' => $b->client_id,
                    'booking_id' => $b->id, 'booking_payment_id' => $p->id, 'invoice_id' => $p->invoice_id,
                    'purpose' => \Illuminate\Support\Str::limit(trim($b->itemTitle().' · '.__('Booking').' '.$b->reference), 250, ''),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
        static::saved(function (BookingPayment $p) {
            $p->booking?->syncPaid();
            $p->invoice?->syncPaid();
        });
        static::deleted(function (BookingPayment $p) {
            CashEntry::withoutGlobalScopes()->where('booking_payment_id', $p->id)->delete();
            $p->booking?->syncPaid();
            $p->invoice?->syncPaid();
        });
    }

    /** set while a payment is created from a cash document, so no second document is made */
    public static bool $withoutCash = false;

    /** cash register for the next automatic cash document (null = the first register, bank for bank transfers) */
    public static ?string $register = null;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }

    public function cashEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CashEntry::class)->withoutGlobalScopes();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
