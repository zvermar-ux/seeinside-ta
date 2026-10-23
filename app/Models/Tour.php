<?php

namespace App\Models;

use App\Enums\TourType;
use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPromotions;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Localized;
use App\Models\Concerns\SyncsToWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use \App\Models\Concerns\HasCosts, BelongsToAgency, HasMedia, HasPromotions, HasSlug, Localized, SyncsToWebsite;

    protected $guarded = ['id'];

    public const TRANSLATABLE = [
        'title', 'subtitle', 'short_description', 'description', 'highlights', 'agenda', 'included', 'not_included',
        'notes', 'important_info', 'cancellation_policy', 'payment_terms', 'baggage_info', 'seo_title', 'seo_description',
    ];

    public static function websiteType(): string
    {
        return 'tour';
    }

    protected function casts(): array
    {
        return array_fill_keys(self::TRANSLATABLE, 'array') + [
            'tour_type' => TourType::class,
            'video_urls' => 'array',
            'galleries' => 'array',
            'links' => 'array',
            'aries_quality' => 'boolean',
            'is_flight' => 'boolean',
            'has_guide' => 'boolean',
            'booking_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'price_adult' => 'decimal:2',
            'price_child' => 'decimal:2',
            'single_supplement' => 'decimal:2',
            'price_from' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'price_regular' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Tour $t) => $t->price_from = $t->computePriceFrom());
    }

    /** Lowest adult price among upcoming departures (or the base price) */
    public function computePriceFrom(): ?float
    {
        $prices = [];
        if ($this->exists) {
            foreach ($this->departures()->whereDate('start_date', '>=', today())->whereNotIn('status', ['sold_out', 'cancelled'])->get() as $d) {
                $p = $d->is_last_minute && $d->last_minute_price !== null ? $d->last_minute_price : ($d->price_adult ?? $this->price_adult);
                if ($p !== null) {
                    $prices[] = (float) $p;
                }
            }
        }
        if (! $prices && $this->price_adult !== null) {
            $prices[] = (float) $this->price_adult;
        }

        // regular (not discounted) lowest price – shown struck through next to the promo price
        $regular = [];
        if ($this->exists) {
            foreach ($this->departures()->whereDate('start_date', '>=', today())->whereNotIn('status', ['sold_out', 'cancelled'])->get() as $d) {
                $p = $d->price_adult ?? $this->price_adult;
                $p !== null && $regular[] = (float) $p;
            }
        }
        if (! $regular && $this->price_adult !== null) {
            $regular[] = (float) $this->price_adult;
        }
        $this->price_regular = $regular ? min($regular) : null;

        return $prices ? $this->promoPrice(min($prices)) : null;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'destination_id');
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class)->orderBy('day_number')->orderBy('sort_order');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class)->orderBy('start_date');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(TourExtra::class)->orderBy('sort_order');
    }

    public function departurePlaces(): BelongsToMany
    {
        return $this->belongsToMany(DeparturePlace::class, 'departure_place_tour')
            ->withPivot(['departure_time', 'surcharge']);
    }

    public function departurePlaceLinks(): HasMany
    {
        return $this->hasMany(DeparturePlaceTour::class);
    }

    public function bookings()
    {
        return $this->morphMany(Booking::class, 'bookable');
    }
}
