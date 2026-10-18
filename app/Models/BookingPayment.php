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
        return ['amount' => 'decimal:2', 'paid_at' => 'date'];
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
        static::saved(fn (BookingPayment $p) => $p->booking?->syncPaid());
        static::deleted(fn (BookingPayment $p) => $p->booking?->syncPaid());
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
