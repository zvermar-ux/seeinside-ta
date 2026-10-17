<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\VisaApplicationStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VisaApplication extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'documents' => 'array',
            'travel_date' => 'date',
            'is_express' => 'boolean',
            'status' => VisaApplicationStatus::class,
            'payment_status' => PaymentStatus::class,
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VisaApplication $a) {
            $a->reference ??= 'V'.now()->format('ym').'-'.Str::upper(Str::random(5));
        });
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
