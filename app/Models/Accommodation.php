<?php

namespace App\Models;

use App\Enums\AccommodationType;
use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPromotions;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Localized;
use App\Models\Concerns\SyncsToWebsite;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Accommodation extends Model
{
    use \App\Models\Concerns\HasCosts, BelongsToAgency, HasMedia, HasPromotions, HasSlug, Localized, SyncsToWebsite;

    protected $guarded = ['id'];

    public const TRANSLATABLE = ['short_description', 'overview', 'house_rules', 'notes', 'cancellation_policy', 'seo_title', 'seo_description'];

    public static function websiteType(): string
    {
        return 'accommodation';
    }

    protected function casts(): array
    {
        return array_fill_keys(self::TRANSLATABLE, 'array') + [
            'type' => AccommodationType::class,
            'stars' => 'integer',
            'board_types' => 'array',
            'video_urls' => 'array',
            'booking_enabled' => 'boolean',
            'use_supplier_availability' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'price_from' => 'decimal:2',
            'price_regular' => 'decimal:2',
            'distance_airport_km' => 'decimal:1',
            'book_by_terms' => 'boolean',
            'pricing_rules' => 'array',
            'galleries' => 'array',
            'lease_from' => 'date',
            'lease_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Accommodation $a) {
            if ($a->exists) {
                $a->price_from = $a->computePriceFrom();
            }
        });
    }

    public function computePriceFrom(): ?float
    {
        if ($this->book_by_terms) {
            $min = TermPrice::query()->whereIn('stay_term_id', $this->stayTerms()->where('is_active', true)->whereDate('date_from', '>=', today())->pluck('id'))
                ->where('stop_sale', false)->min('price');

            $this->price_regular = $min;

            return $min === null ? null : $this->promoPrice((float) $min);
        }
        $roomIds = $this->rooms()->where('is_active', true)->pluck('id');
        $rate = RoomRate::whereIn('accommodation_room_id', $roomIds)->whereDate('date_to', '>=', today())->min('price');
        $base = $this->rooms()->where('is_active', true)->min('price');
        $min = collect([$rate, $base])->filter(fn ($v) => $v !== null)->min();
        $this->price_regular = $min;

        return $min === null ? null : $this->promoPrice((float) $min);
    }

    /**
     * Rooms bookable in one fixed term: price from the term price list, free units from the availability.
     *
     * @return array<int, array{room_id:int, room:string, board:?string, nights:int, total:float, currency:string, term_id:int, available:?int, price:?float}>
     */
    public function termOffers(StayTerm $term, int $adults = 2, int $children = 0, bool $includeFull = false): array
    {
        $svc = app(\App\Services\Availability\AvailabilityService::class);
        $availability = $svc->forAccommodation($this, $term->date_from, $term->date_to->copy()->subDay(), false);
        $prices = $term->prices()->get()->keyBy('accommodation_room_id');
        $perPerson = $this->term_price_type === 'per_person';
        $out = [];
        foreach ($this->rooms()->where('is_active', true)->get() as $room) {
            $tp = $prices->get($room->id);
            if (! $tp || $tp->price === null || $tp->stop_sale) {
                continue;
            }
            if (! $includeFull && ($adults > $room->max_adults || $children > $room->max_children)) {
                continue;
            }
            $free = $svc->freeForStay($availability, $room->id, $term->date_from->toDateString(), $term->date_to->toDateString());
            if (! $includeFull && $free !== null && $free < 1) {
                continue;
            }
            $total = (float) $tp->price * ($perPerson ? max(1, $adults + $children) : 1);
            $out[] = [
                'room_id' => $room->id, 'room' => $room->tr('name'), 'room_type' => $room->room_type?->value, 'board' => $room->board?->value,
                'nights' => $term->nights(), 'available' => $free, 'term_id' => $term->id, 'price' => (float) $tp->price, 'price_type' => $this->term_price_type,
                'total' => round($this->promoPrice($total), 2), 'regular' => round($total, 2), 'currency' => $this->currency,
            ];
        }
        usort($out, fn ($a, $b) => $a['total'] <=> $b['total']);

        return $out;
    }

    /** Upcoming active terms with every room's price and free units (website table, booking form) */
    public function termTable(?Carbon $from = null): array
    {
        $from ??= today();
        $rooms = $this->rooms()->where('is_active', true)->get();
        $rows = [];
        foreach ($this->stayTerms()->where('is_active', true)->whereDate('date_from', '>=', $from)->with('prices')->limit(60)->get() as $t) {
            $offers = collect($this->termOffers($t, 1, 0, true))->keyBy('room_id');
            $rows[] = [
                'id' => $t->id, 'from' => $t->date_from->toDateString(), 'to' => $t->date_to->toDateString(), 'nights' => $t->nights(), 'label' => $t->label, 'note' => $t->note,
                'rooms' => $rooms->mapWithKeys(fn ($r) => [$r->id => $offers->has($r->id) ? [
                    'price' => $offers[$r->id]['total'], 'regular' => $offers[$r->id]['regular'], 'free' => $offers[$r->id]['available'],
                ] : null])->all(),
            ];
        }

        return ['price_type' => $this->term_price_type, 'currency' => $this->currency,
            'rooms' => $rooms->map(fn ($r) => ['id' => $r->id, 'name' => $r->trAll('name'), 'board' => $r->board?->value, 'max_adults' => $r->max_adults, 'max_children' => $r->max_children])->values()->all(),
            'terms' => $rows];
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

    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    public function stayTerms(): HasMany
    {
        return $this->hasMany(StayTerm::class)->orderBy('date_from');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->orderBy('sort_order');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(AccommodationRoom::class)->orderBy('sort_order');
    }

    /**
     * Price offers for a stay: one line per room that can host the party.
     *
     * @return array<int, array{room_id:int, room:string, board:?string, nights:int, total:float, currency:string, per_night:array}>
     */
    public function quote(string $from, string $to, int $adults = 2, int $children = 0, bool $checkAvailability = true): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $nights = (int) $start->diffInDays($end);
        if ($nights < 1) {
            return [];
        }
        // fixed terms: only a whole term can be booked
        if ($this->book_by_terms) {
            $term = $this->stayTerms()->where('is_active', true)->whereDate('date_from', $start)->whereDate('date_to', $end)->first();

            return $term ? $this->termOffers($term, $adults, $children, ! $checkAvailability) : [];
        }

        $availabilityService = app(\App\Services\Availability\AvailabilityService::class);
        $availability = $availabilityService->forAccommodation($this, $start, $end->copy()->subDay(), false);

        $offers = [];
        foreach ($this->rooms()->where('is_active', true)->with('rates')->get() as $room) {
            if ($adults > $room->max_adults || $children > $room->max_children) {
                continue;
            }
            $free = $availabilityService->freeForStay($availability, $room->id, $start->toDateString(), $end->toDateString());
            if ($checkAvailability && $free !== null && $free < 1) {
                continue;   // fully booked on at least one night
            }

            $total = 0.0;
            $ok = true;
            $minNights = 1;
            foreach (CarbonPeriod::create($start, $end->copy()->subDay()) as $night) {
                $rate = $room->rates->first(fn ($r) => $night->between($r->date_from, $r->date_to));
                $price = $rate?->price ?? $room->price;
                if ($price === null) {
                    $ok = false;
                    break;
                }
                $minNights = max($minNights, (int) ($rate?->min_nights ?? 1));
                $perPerson = ($rate?->price_type ?? $room->price_type) === 'per_person';
                $total += (float) $price * ($perPerson ? max(1, $adults) : 1);
            }
            if (! $ok || $nights < $minNights) {
                continue;
            }

            $total = $this->promoPrice($total);
            $offers[] = [
                'room_id' => $room->id,
                'room' => $room->tr('name'),
                'room_type' => $room->room_type?->value,
                'board' => $room->board?->value,
                'nights' => $nights,
                'available' => $free,
                'total' => round($total, 2),
                'currency' => $this->currency,
            ];
        }

        usort($offers, fn ($a, $b) => $a['total'] <=> $b['total']);

        return $offers;
    }
}
