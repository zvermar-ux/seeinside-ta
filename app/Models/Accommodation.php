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
    use BelongsToAgency, HasMedia, HasPromotions, HasSlug, Localized, SyncsToWebsite;

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
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'price_from' => 'decimal:2',
            'distance_airport_km' => 'decimal:1',
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
        $roomIds = $this->rooms()->where('is_active', true)->pluck('id');
        $rate = RoomRate::whereIn('accommodation_room_id', $roomIds)->whereDate('date_to', '>=', today())->min('price');
        $base = $this->rooms()->where('is_active', true)->min('price');
        $min = collect([$rate, $base])->filter(fn ($v) => $v !== null)->min();

        return $min === null ? null : $this->promoPrice((float) $min);
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
    public function quote(string $from, string $to, int $adults = 2, int $children = 0): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $nights = (int) $start->diffInDays($end);
        if ($nights < 1) {
            return [];
        }

        $offers = [];
        foreach ($this->rooms()->where('is_active', true)->with('rates')->get() as $room) {
            if ($adults > $room->max_adults || $children > $room->max_children) {
                continue;
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
                'total' => round($total, 2),
                'currency' => $this->currency,
            ];
        }

        usort($offers, fn ($a, $b) => $a['total'] <=> $b['total']);

        return $offers;
    }
}
