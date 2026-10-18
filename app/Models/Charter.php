<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Charter extends Model
{
    use \App\Models\Concerns\HasCosts, BelongsToAgency;

    protected $guarded = ['id'];

    /** Charter changes are pushed to the website as "charter.updated" / "charter.deleted" webhooks */
    public static function websiteType(): string
    {
        return 'charter';
    }

    protected static function booted(): void
    {
        static::saved(fn (Charter $c) => \App\Services\Website\WebsiteNotifier::changed($c));
        static::deleted(fn (Charter $c) => \App\Services\Website\WebsiteNotifier::deleted($c));
    }

    /** Used as the offer name in bookings */
    public function getNameAttribute(): string
    {
        return trim(($this->destination ?: $this->region).' · '.$this->departure_date?->format('d.m.Y'), ' ·');
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    public function seatsLeft(): ?int
    {
        if (! $this->seats) {
            return null;
        }
        $taken = $this->bookings()->whereNotIn('status', [\App\Enums\BookingStatus::Cancelled->value])->get()
            ->sum(fn (Booking $b) => max(1, (int) $b->adults + (int) $b->children));

        return max(0, $this->seats - $taken);
    }

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'transport_only_price' => 'decimal:2',
        ];
    }
}
