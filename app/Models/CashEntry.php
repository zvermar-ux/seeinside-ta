<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasYearlyNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** One cash register document: receipt (Каса прими) or payout (Каса исплати) */
class CashEntry extends Model
{
    use BelongsToAgency, HasYearlyNumber;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_at' => 'datetime', 'amount' => 'decimal:2', 'amount_base' => 'decimal:2', 'exchange_rate' => 'decimal:4'];
    }

    protected function numberScope(): array
    {
        return ['direction'];
    }

    protected function numberDateColumn(): string
    {
        return 'entry_at';
    }

    public static function categories(): array
    {
        return ['arrangement' => __('Arrangement'), 'air_ticket' => __('Air ticket'), 'bus_ticket' => __('Bus ticket'), 'insurance' => __('Insurance'), 'visa' => __('Visa'),
            'supplier' => __('Payment to supplier'), 'refund' => __('Refund to client'), 'expense' => __('Agency expense'), 'deposit' => __('Bank deposit / withdrawal'), 'other' => __('Other')];
    }

    public static function methods(): array
    {
        return ['cash' => __('Cash'), 'card' => __('Card'), 'bank' => __('Bank transfer')];
    }

    protected static function booted(): void
    {
        static::creating(function (CashEntry $e) {
            $e->created_by ??= Auth::id();
            $e->entry_at ??= now();
        });
        static::saving(function (CashEntry $e) {
            $agency = Agency::find($e->agency_id);
            if ($agency) {
                $e->amount_base = $agency->toBase((float) $e->amount, $e->currency, $e->exchange_rate ? (float) $e->exchange_rate : null);
            }
        });
        // keep "paid" of supplier invoices / invoices in step with the cash documents
        $sync = function (CashEntry $e) {
            if ($e->supplier_invoice_id && ($si = SupplierInvoice::withoutGlobalScopes()->find($e->supplier_invoice_id))) {
                \App\Services\Finance\Finance::syncSupplier($si);
            }
            if ($e->invoice_id && ! $e->booking_payment_id && ($i = Invoice::withoutGlobalScopes()->find($e->invoice_id))) {
                $i->syncPaid();
            }
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function number(): string
    {
        return $this->seq.'/'.substr((string) $this->year, -2);
    }

    public function title(): string
    {
        return ($this->direction === 'in' ? __('Cash receipt') : __('Cash payout')).' '.__('no.').' '.$this->number();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class, 'booking_payment_id');
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
