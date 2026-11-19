<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One attempt of a card payment on the bank's page (Halk Bank) for a booking or a visa application */
class OnlinePayment extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'booking_amount' => 'decimal:2', 'response' => 'array', 'paid_at' => 'datetime'];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function url(): string
    {
        return route('pay.show', $this->token);
    }
}
