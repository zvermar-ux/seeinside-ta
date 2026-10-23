<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/** Incoming invoice from a supplier (hotel, bus company, tour operator…) – the real cost of a booking or offer */
class SupplierInvoice extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'exchange_rate' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::creating(fn (SupplierInvoice $i) => $i->created_by ??= Auth::id());
        static::saving(function (SupplierInvoice $i) {
            if (blank($i->supplier_name)) {
                $i->supplier_name = $i->tourOperator?->name ?? $i->client?->display_name ?? '—';
            }
            // linked to a booking → the booking's offer
            if ($i->booking_id && ! $i->costable_type && ($b = Booking::withoutGlobalScopes()->find($i->booking_id))) {
                $i->costable_type = $b->bookable_type;
                $i->costable_id = $b->bookable_id;
            }
        });
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }
}
