<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fixed stay of an accommodation (e.g. 15.05–22.05) – booked as a whole, with a price per room */
class StayTerm extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (StayTerm $t) => TermPrice::$quiet || $t->accommodation?->touch());
        static::deleted(fn (StayTerm $t) => TermPrice::$quiet || $t->accommodation?->touch());
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TermPrice::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function nights(): int
    {
        return (int) $this->date_from->diffInDays($this->date_to);
    }

    public function label(): string
    {
        return $this->label ?: $this->date_from->format('d.m').' – '.$this->date_to->format('d.m.Y');
    }
}
