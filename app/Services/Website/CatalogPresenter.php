<?php

namespace App\Services\Website;

use App\Models\Accommodation;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\VisaType;
use Illuminate\Support\Facades\Storage;

/** Shapes catalog records for the website API (both languages included) */
class CatalogPresenter
{
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function gallery($model): array
    {
        return $model->media->map(fn (Media $m) => [
            'url' => static::url($m->path),
            'caption' => ['mk' => $m->caption['mk'] ?? null, 'en' => $m->caption['en'] ?? null],
            'is_main' => $m->is_main,
        ])->values()->all();
    }

    public static function group(?Group $g): ?array
    {
        return $g ? ['id' => $g->id, 'name' => ['mk' => $g->getTranslation('name', 'mk', false) ?: $g->display_name, 'en' => $g->getTranslation('name', 'en', false) ?: null]] : null;
    }

    protected static function money($v): ?float
    {
        return $v === null ? null : (float) $v;
    }

    /* ---------------- tours ---------------- */

    public static function tourSummary(Tour $t): array
    {
        $next = $t->departures->first(fn (TourDeparture $d) => $d->start_date->gte(today()));

        return [
            'type' => 'tour',
            'id' => $t->id,
            'slug' => $t->slug,
            'title' => $t->trAll('title'),
            'subtitle' => $t->trAll('subtitle'),
            'short_description' => $t->trAll('short_description'),
            'tour_type' => ['value' => $t->tour_type?->value, 'label' => $t->tour_type?->labels()],
            'location' => $t->location,
            'country' => $t->country,
            'destination' => static::group($t->destination),
            'group' => static::group($t->group),
            'duration' => ['days' => $t->duration_days, 'nights' => $t->duration_nights],
            'price_from' => static::money($t->price_from),
            'currency' => $t->currency,
            'next_departure' => $next?->start_date->toDateString(),
            'image' => static::url($t->cover_image),
            'is_flight' => $t->is_flight,
            'aries_quality' => $t->aries_quality,
            'has_guide' => $t->has_guide,
            'is_featured' => $t->is_featured,
            'promotions' => $t->promoArray(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    public static function tour(Tour $t): array
    {
        return static::tourSummary($t) + [
            'description' => $t->trAll('description'),
            'highlights' => $t->trAll('highlights', true),
            'agenda' => $t->trAll('agenda'),
            'included' => $t->trAll('included', true),
            'not_included' => $t->trAll('not_included', true),
            'notes' => $t->trAll('notes'),
            'important_info' => $t->trAll('important_info'),
            'baggage_info' => $t->trAll('baggage_info'),
            'payment_terms' => $t->trAll('payment_terms'),
            'cancellation_policy' => $t->trAll('cancellation_policy'),
            'group_size' => ['min' => $t->group_size_min, 'max' => $t->group_size_max],
            'map' => $t->map_location,
            'airline' => $t->airline ? ['name' => $t->airline->name, 'iata' => $t->airline->iata_code, 'logo' => static::url($t->airline->logo)] : null,
            'prices' => [
                'adult' => static::money($t->price_adult),
                'child' => static::money($t->price_child),
                'child_age' => ['from' => $t->child_age_from, 'to' => $t->child_age_to],
                'single_supplement' => static::money($t->single_supplement),
                'deposit_percent' => static::money($t->deposit_percent),
            ],
            'departures' => $t->departures->filter(fn ($d) => $d->start_date->gte(today()))->map(fn (TourDeparture $d) => [
                'id' => $d->id,
                'start_date' => $d->start_date->toDateString(),
                'end_date' => $d->end_date?->toDateString(),
                'price_adult' => $d->adultPrice(),
                'price_child' => $d->childPrice(),
                'seats_left' => $d->seatsLeft(),
                'status' => $d->status?->value,
                'status_label' => $d->status?->labels(),
                'is_last_minute' => $d->is_last_minute,
                'note' => $d->note,
            ])->values()->all(),
            'departure_places' => $t->departurePlaceLinks->map(fn ($l) => [
                'id' => $l->departure_place_id,
                'name' => $l->departurePlace?->trAll('name'),
                'time' => $l->departure_time,
                'surcharge' => (float) $l->surcharge,
            ])->values()->all(),
            'program' => $t->days->map(fn ($d) => [
                'day' => $d->day_number,
                'title' => $d->trAll('title'),
                'description' => $d->trAll('description'),
                'meals' => $d->meals,
                'overnight' => $d->overnight,
            ])->values()->all(),
            'extras' => $t->extras->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->trAll('name'),
                'description' => $e->trAll('description'),
                'price' => (float) $e->price,
                'price_type' => $e->price_type,
                'kind' => $e->kind,
                'optional' => $e->is_optional,
            ])->values()->all(),
            'videos' => array_values($t->video_urls ?? []),
            'links' => array_values($t->links ?? []),
            'program_pdf' => static::url($t->program_pdf),
            'gallery' => static::gallery($t),
            'booking' => ['enabled' => $t->booking_enabled, 'mode' => $t->booking_mode],
            'seo' => ['title' => $t->trAll('seo_title'), 'description' => $t->trAll('seo_description')],
        ];
    }

    /* ---------------- events ---------------- */

    public static function eventSummary(Event $e): array
    {
        return [
            'type' => 'event',
            'id' => $e->id,
            'slug' => $e->slug,
            'title' => $e->trAll('title'),
            'short_description' => $e->trAll('short_description'),
            'category' => ['value' => $e->category?->value, 'label' => $e->category?->labels()],
            'status' => ['value' => $e->status?->value, 'label' => $e->status?->labels()],
            'performer' => $e->performer,
            'venue' => $e->venue,
            'city' => $e->city,
            'country' => $e->country,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'price_from' => static::money($e->price_from),
            'currency' => $e->currency,
            'image' => static::url($e->cover_image),
            'is_featured' => $e->is_featured,
            'promotions' => $e->promoArray(),
            'updated_at' => $e->updated_at?->toIso8601String(),
        ];
    }

    public static function event(Event $e): array
    {
        return static::eventSummary($e) + [
            'description' => $e->trAll('description'),
            'included' => $e->trAll('included', true),
            'not_included' => $e->trAll('not_included', true),
            'notes' => $e->trAll('notes'),
            'ends_at' => $e->ends_at?->toIso8601String(),
            'doors_open' => $e->doors_open,
            'age_restriction' => $e->age_restriction,
            'organizer' => $e->organizer,
            'address' => $e->address,
            'map' => $e->map_location,
            'tickets' => $e->tickets->where('is_active', true)->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->trAll('name'), 'description' => $t->trAll('description'),
                'price' => (float) $t->price, 'available' => $t->available(),
            ])->values()->all(),
            'external_ticket_url' => $e->external_ticket_url,
            'tour' => $e->tour ? ['id' => $e->tour->id, 'slug' => $e->tour->slug, 'title' => $e->tour->trAll('title')] : null,
            'videos' => array_values($e->video_urls ?? []),
            'gallery' => static::gallery($e),
            'booking' => ['enabled' => $e->booking_enabled],
            'seo' => ['title' => $e->trAll('seo_title'), 'description' => $e->trAll('seo_description')],
        ];
    }

    /* ---------------- accommodations ---------------- */

    public static function accommodationSummary(Accommodation $a): array
    {
        return [
            'type' => 'accommodation',
            'id' => $a->id,
            'slug' => $a->slug,
            'name' => $a->name,
            'accommodation_type' => ['value' => $a->type?->value, 'label' => $a->type?->labels()],
            'stars' => $a->stars,
            'place' => $a->place,
            'region' => $a->region,
            'country' => $a->country,
            'destination' => static::group($a->destination),
            'short_description' => $a->trAll('short_description'),
            'board_types' => array_values($a->board_types ?? []),
            'price_from' => static::money($a->price_from),
            'currency' => $a->currency,
            'image' => static::url($a->cover_image),
            'amenities' => $a->amenities->map(fn ($m) => ['key' => $m->key, 'name' => $m->trAll('name'), 'icon' => $m->icon])->values()->all(),
            'is_featured' => $a->is_featured,
            'promotions' => $a->promoArray(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    public static function accommodation(Accommodation $a): array
    {
        return static::accommodationSummary($a) + [
            'overview' => $a->trAll('overview'),
            'house_rules' => $a->trAll('house_rules'),
            'notes' => $a->trAll('notes'),
            'cancellation_policy' => $a->trAll('cancellation_policy'),
            'address' => $a->address,
            'map' => $a->map_location,
            'check_in' => $a->check_in_time,
            'check_out' => $a->check_out_time,
            'distances' => [
                'beach_m' => $a->distance_beach_m, 'center_m' => $a->distance_center_m,
                'ski_lift_m' => $a->distance_ski_lift_m, 'airport_km' => $a->distance_airport_km !== null ? (float) $a->distance_airport_km : null,
            ],
            'rooms' => $a->rooms->where('is_active', true)->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->trAll('name'),
                'room_type' => ['value' => $r->room_type?->value, 'label' => $r->room_type?->labels()],
                'board' => ['value' => $r->board?->value, 'label' => $r->board?->labels()],
                'max_adults' => $r->max_adults,
                'max_children' => $r->max_children,
                'beds' => $r->beds,
                'size_m2' => $r->size_m2,
                'description' => $r->trAll('description'),
                'amenities' => array_values($r->room_amenities ?? []),
                'price' => static::money($r->price),
                'price_type' => $r->price_type,
                'rates' => $r->rates->filter(fn ($x) => $x->date_to->gte(today()))->map(fn ($x) => [
                    'from' => $x->date_from->toDateString(), 'to' => $x->date_to->toDateString(),
                    'price' => (float) $x->price, 'price_type' => $x->price_type, 'min_nights' => $x->min_nights,
                    'is_last_minute' => $x->is_last_minute,
                ])->values()->all(),
                'images' => static::gallery($r),
            ])->values()->all(),
            'price_list_pdf' => static::url($a->price_list_pdf),
            'videos' => array_values($a->video_urls ?? []),
            'gallery' => static::gallery($a),
            'booking' => ['enabled' => $a->booking_enabled, 'mode' => $a->booking_mode],
            'seo' => ['title' => $a->trAll('seo_title'), 'description' => $a->trAll('seo_description')],
        ];
    }

    /* ---------------- visas ---------------- */

    public static function visaSummary(VisaType $v): array
    {
        return [
            'type' => 'visa',
            'id' => $v->id,
            'slug' => $v->slug,
            'country' => $v->country,
            'country_code' => $v->country_code,
            'name' => $v->trAll('name'),
            'category' => ['value' => $v->category?->value, 'label' => $v->category?->labels()],
            'short_description' => $v->trAll('short_description'),
            'price' => $v->totalPrice(),
            'currency' => $v->currency,
            'image' => static::url($v->cover_image),
            'promotions' => $v->promoArray(),
            'updated_at' => $v->updated_at?->toIso8601String(),
        ];
    }

    public static function visa(VisaType $v): array
    {
        return static::visaSummary($v) + [
            'description' => $v->trAll('description'),
            'requirements' => $v->trAll('requirements', true),
            'processing_time' => $v->trAll('processing_time'),
            'validity' => $v->trAll('validity'),
            'fees' => [
                'service' => (float) $v->service_fee, 'consular' => (float) $v->consular_fee, 'express' => (float) $v->express_fee,
            ],
            'online_payment' => $v->online_payment,
            'questionnaire' => array_values($v->questionnaire ?? []),
            'required_documents' => array_values($v->required_documents ?? []),
            'gallery' => static::gallery($v),
            'seo' => ['title' => $v->trAll('seo_title'), 'description' => $v->trAll('seo_description')],
        ];
    }
}
