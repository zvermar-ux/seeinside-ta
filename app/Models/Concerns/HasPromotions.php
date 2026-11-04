<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Last minute / first minute offers */
trait HasPromotions
{
    public function initializeHasPromotions(): void
    {
        $this->mergeCasts([
            'is_last_minute' => 'boolean',
            'last_minute_price' => 'decimal:2',
            'last_minute_discount_percent' => 'decimal:2',
            'last_minute_until' => 'date',
            'first_minute_discount' => 'decimal:2',
            'first_minute_until' => 'date',
            'early_booking_tiers' => 'array',
        ]);
    }

    public function scopeLastMinute(Builder $query): Builder
    {
        return $query->where('is_last_minute', true)
            ->where(fn ($q) => $q->whereNull('last_minute_until')->orWhereDate('last_minute_until', '>=', today()));
    }

    public function isLastMinuteActive(): bool
    {
        return $this->is_last_minute && (! $this->last_minute_until || $this->last_minute_until->endOfDay()->isFuture());
    }

    /** Early booking in steps (20% until 30.11, 15% until 31.12 …): the steps still to come, sorted by date */
    public function earlyBookingTiers(): array
    {
        return collect((array) $this->early_booking_tiers)
            ->filter(fn ($t) => is_array($t) && (float) ($t['percent'] ?? 0) > 0 && ! empty($t['until']))
            ->map(fn ($t) => ['percent' => (float) $t['percent'], 'until' => \Illuminate\Support\Carbon::parse($t['until'])->toDateString()])
            ->filter(fn ($t) => $t['until'] >= today()->toDateString())
            ->sortBy('until')->values()->all();
    }

    /** The step valid today, or null */
    public function currentEarlyBookingTier(): ?array
    {
        return $this->earlyBookingTiers()[0] ?? null;
    }

    public function isFirstMinuteActive(): bool
    {
        if ($this->currentEarlyBookingTier()) {
            return true;
        }

        return filled($this->first_minute_discount) && (! $this->first_minute_until || $this->first_minute_until->endOfDay()->isFuture());
    }

    /** Apply the active promotion to a regular price */
    public function promoPrice(?float $regular): ?float
    {
        if ($regular === null) {
            return null;
        }
        if ($this->isLastMinuteActive()) {
            if ($this->last_minute_price !== null) {
                return (float) $this->last_minute_price;
            }
            if ($this->last_minute_discount_percent) {
                return round($regular * (1 - $this->last_minute_discount_percent / 100), 2);
            }
        }
        if ($tier = $this->currentEarlyBookingTier()) {
            return round($regular * (1 - $tier['percent'] / 100), 2);
        }
        if ($this->isFirstMinuteActive()) {
            return $this->first_minute_discount_type === 'percent'
                ? round($regular * (1 - $this->first_minute_discount / 100), 2)
                : max(0, round($regular - (float) $this->first_minute_discount, 2));
        }

        return $regular;
    }

    public function promoArray(): array
    {
        return [
            'last_minute' => $this->isLastMinuteActive() ? [
                'price' => $this->last_minute_price !== null ? (float) $this->last_minute_price : null,
                'discount_percent' => $this->last_minute_discount_percent !== null ? (float) $this->last_minute_discount_percent : null,
                'until' => $this->last_minute_until?->toDateString(),
            ] : null,
            'first_minute' => $this->isFirstMinuteActive() ? [
                'discount' => ($t = $this->currentEarlyBookingTier()) ? $t['percent'] : (float) $this->first_minute_discount,
                'type' => $t ? 'percent' : $this->first_minute_discount_type,
                'until' => $t ? $t['until'] : $this->first_minute_until?->toDateString(),
                'steps' => $this->earlyBookingTiers(),
                'label' => $this->first_minute_label ?: 'Early Booking',
            ] : null,
        ];
    }
}
