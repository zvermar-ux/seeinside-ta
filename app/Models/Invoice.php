<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasYearlyNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/** Outgoing proforma (offer) or invoice */
class Invoice extends Model
{
    use BelongsToAgency, HasYearlyNumber;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'sent_at' => 'datetime', 'lines' => 'array',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'vat_amount' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'exchange_rate' => 'decimal:4',
        ];
    }

    protected function numberScope(): array
    {
        return ['type'];
    }

    protected function numberDateColumn(): string
    {
        return 'issue_date';
    }

    protected function numberColumn(): ?string
    {
        return 'number';
    }

    public static function statuses(): array
    {
        return ['draft' => __('Draft'), 'issued' => __('Issued'), 'partial' => __('Partly paid'), 'paid' => __('Paid'), 'cancelled' => __('Cancelled')];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $i) {
            $i->created_by ??= Auth::id();
            $i->issue_date ??= now();
        });
        static::saving(function (Invoice $i) {
            $i->recalc();
            if ($i->status !== 'cancelled' && $i->status !== 'draft') {
                $paid = (float) $i->paid_amount;
                $converted = $i->type === 'proforma' && $i->exists && self::where('proforma_id', $i->id)->exists();
                $i->status = $converted ? 'paid' : ($paid <= 0 ? 'issued' : ($paid + 0.009 < (float) $i->total ? 'partial' : 'paid'));
            }
        });
    }

    /** Totals from the lines: each line has qty, price, vat (%) */
    public function recalc(): void
    {
        $sub = 0.0;
        $vat = 0.0;
        $lines = [];
        foreach ((array) $this->lines as $l) {
            $qty = (float) ($l['qty'] ?? 1);
            $price = (float) ($l['price'] ?? 0);
            $net = round($qty * $price, 2);
            $rate = (float) ($l['vat'] ?? 0);
            $l['total'] = $net;
            $sub += $net;
            $vat += round($net * $rate / 100, 2);
            $lines[] = $l;
        }
        $this->lines = $lines;
        $this->subtotal = round($sub, 2);
        $this->vat_amount = round($vat, 2);
        $this->total = round($sub - (float) $this->discount + $vat, 2);
    }

    /** paid = payments linked to this invoice (booking payments) + cash documents without a booking payment */
    public function syncPaid(): void
    {
        $paid = (float) BookingPayment::withoutGlobalScopes()->where('invoice_id', $this->id)->sum('amount');
        // invoices are usually in MKD while bookings are in EUR: payments in the booking currency are converted
        if ($paid && $this->booking && $this->booking->currency !== $this->currency && $this->currency === 'MKD') {
            $paid = $this->booking->inBase($paid);
        }
        $cash = (float) CashEntry::withoutGlobalScopes()->where('invoice_id', $this->id)->whereNull('booking_payment_id')->where('direction', 'in')->sum('amount');
        $this->paid_amount = round($paid + $cash, 2);
        $this->save();
    }

    public function balance(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    public function title(): string
    {
        return ($this->type === 'proforma' ? __('Proforma invoice') : __('Invoice')).' '.$this->number;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(self::class, 'proforma_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }
}
