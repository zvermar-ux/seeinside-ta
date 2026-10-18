<?php

namespace App\Services\Aries;

use App\Enums\AccommodationType;
use App\Enums\Board;
use App\Enums\ObjectType;
use App\Enums\RoomType;
use App\Enums\TourType;
use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\TourResource;
use App\Models\Accommodation;
use App\Models\Agency;
use App\Models\Amenity;
use App\Models\Tour;
use App\Models\TravelObject;
use App\Services\Ai\Ai;
use App\Services\Ai\AiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Create website offer with AI": the imported Aries record (and its sibling records – other rooms /
 * services of the same hotel) is read by AI, which puts every piece of information into the right field
 * of a new tour or hotel: titles, texts in MK + EN, type, stars, board, rooms, amenities, distances,
 * program by days, included / not included… Only facts from the source – nothing invented.
 */
class AriesAiConverter
{
    /** @return array{url: string, filled: int, record: \Illuminate\Database\Eloquent\Model} */
    public static function convert(TravelObject $o, string $extra = '', string $tone = 'friendly'): array
    {
        $agencyId = $o->agency_id;
        if (! Ai::available($agencyId, 'assistant')) {
            throw new AiException(Ai::unavailableReason($agencyId, 'assistant') ?? __('AI is not available.'));
        }
        $isTour = $o->type === ObjectType::Arrangement;
        $source = static::source($o);
        $agency = Agency::query()->whereKey($agencyId)->value('name') ?: 'travel agency';
        $toneText = ['friendly' => 'warm and friendly', 'premium' => 'elegant, premium', 'short' => 'short and factual', 'fun' => 'playful, for young travellers'][$tone] ?? 'warm and friendly';

        $system = "You are an experienced product manager and copywriter of the travel agency \"{$agency}\" from North Macedonia. "
            .'You turn raw supplier data into a complete, well structured website offer. Put every piece of information into the field where it belongs. '
            .'Use ONLY facts from the source data – never invent prices, dates, distances, star ratings, amenities or services. When something is unknown use null (or an empty array). '
            .'You may reword, structure and translate. Macedonian texts in correct standard Macedonian (Cyrillic); English in natural British English. '
            ."Tone: {$toneText}. HTML fields: only <p>, <ul>, <li>, <strong>, <em>, <h3>. Return JSON only.";

        $prompt = ($isTour ? static::tourSpec() : static::hotelSpec())
            ."\n\nSOURCE DATA (imported from the supplier system, JSON):\n".json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            .(filled($extra) ? "\n\nINSTRUCTIONS FROM THE OPERATOR:\n".Str::limit($extra, 3000) : '');

        $out = Ai::json($agencyId, 'assistant', $system, [['role' => 'user', 'content' => $prompt]], $isTour ? 9000 : 7000, null, 'Aries → AI: '.Str::limit($o->name, 60));

        return DB::transaction(fn () => $isTour ? static::makeTour($o, $out) : static::makeHotel($o, $out));
    }

    /** Everything we know about the record, plus its sibling records (other rooms / services of the same object) */
    public static function source(TravelObject $o): array
    {
        $clean = fn ($v) => is_string($v) ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $v))))) : $v;
        $tr = fn (string $attr) => array_filter(array_map(fn ($v) => Str::limit($clean($v), 9000), (array) $o->getTranslations($attr)));
        $siblings = TravelObject::query()->where('agency_id', $o->agency_id)->where('type', $o->type)
            ->where('tour_operator_id', $o->tour_operator_id)->where('external_source', $o->external_source)
            ->where('external_id', $o->external_id)->whereNotNull('external_id')->whereKeyNot($o->id)->limit(30)->get();

        return array_filter([
            'kind' => $o->type?->value,
            'name' => $o->name,
            'room_or_service' => $o->room,
            'board' => $o->board,
            'stars' => $o->stars,
            'place' => $o->place, 'region' => $o->region, 'country' => $o->country,
            'map_location' => $o->map_location,
            'tour_operator' => $o->tourOperator?->name,
            'group' => $o->group?->display_name, 'destination' => $o->destination?->display_name,
            'description' => $tr('description'),
            'service_description' => $tr('service_description'),
            'photo_captions' => $o->images->pluck('caption')->filter()->unique()->values()->take(30)->all(),
            'other_rooms_or_services_of_the_same_object' => $siblings->map(fn (TravelObject $s) => array_filter([
                'room_or_service' => $s->room, 'board' => $s->board,
                'service_description' => Str::limit($clean((string) collect($s->getTranslations('service_description'))->first()), 600),
            ]))->filter()->values()->all(),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    protected static function opts(string $enum): string
    {
        return implode(' | ', array_map(fn ($c) => $c->value, $enum::cases()));
    }

    protected static function tourSpec(): string
    {
        return 'Create an ORGANIZED TOUR. Return this JSON (mk/en objects for texts):
{
 "title": {"mk": "", "en": ""},                       // attractive title
 "subtitle": {"mk": "", "en": ""},                    // route, e.g. "Скопје – Рим – Фиренца – Скопје"
 "tour_type": "'.static::opts(TourType::class).'",
 "location": "", "country": "",                       // main place(s) and country (country in Macedonian)
 "duration_days": null, "duration_nights": null,
 "is_flight": false, "has_guide": false,               // only true when the source says so
 "short_description": {"mk": "", "en": ""},           // 1–2 sentences
 "description": {"mk": "<p>…</p>", "en": "<p>…</p>"}, // 2–4 paragraphs
 "highlights": {"mk": [], "en": []},                  // 4–8 short items
 "included": {"mk": [], "en": []}, "not_included": {"mk": [], "en": []},
 "important_info": {"mk": "", "en": ""},              // html: documents, visa, climate, currency…
 "payment_terms": {"mk": "", "en": ""}, "cancellation_policy": {"mk": "", "en": ""}, "baggage_info": {"mk": "", "en": ""},
 "notes": {"mk": "", "en": ""},
 "days": [{"day": 1, "title": {"mk": "", "en": ""}, "description": {"mk": "<p>…</p>", "en": "<p>…</p>"}, "meals": "B/HB/…", "overnight": "city"}],
 "seo_title": {"mk": "", "en": ""}, "seo_description": {"mk": "", "en": ""}
}
"days" only when the source contains a program; otherwise []. Empty strings / [] for unknown texts.';
    }

    protected static function hotelSpec(): string
    {
        $amen = Amenity::query()->where('is_active', true)->get()->map(fn ($a) => $a->key.' ('.$a->tr('name', 'en').')')->implode(', ');
        $roomAmen = Amenity::query()->where('is_active', true)->whereIn('category', ['room', 'general'])->pluck('key')->implode(', ');

        return 'Create a HOTEL / APARTMENT record. Return this JSON:
{
 "name": "",                                          // official name, without stars or place unless part of the name
 "type": "'.static::opts(AccommodationType::class).'",
 "stars": null,                                       // 1–5, only if known
 "address": null, "place": "", "region": "", "country": "",
 "short_description": {"mk": "", "en": ""},          // 1–2 sentences teaser
 "overview": {"mk": "<p>…</p>", "en": "<p>…</p>"},   // location, rooms, beach, food, facilities
 "house_rules": {"mk": "", "en": ""}, "notes": {"mk": "", "en": ""}, "cancellation_policy": {"mk": "", "en": ""},
 "check_in_time": null, "check_out_time": null,       // "14:00"
 "distance_beach_m": null, "distance_center_m": null, "distance_ski_lift_m": null, "distance_airport_km": null,  // numbers, only if stated
 "board_types": [],                                   // any of: '.static::opts(Board::class).'
 "amenities": [],                                     // keys from this list only: '.$amen.'
 "rooms": [{"name": {"mk": "", "en": ""}, "room_type": "'.static::opts(RoomType::class).'", "board": "'.static::opts(Board::class).'",
            "max_adults": 2, "max_children": 0, "beds": "", "size_m2": null, "description": {"mk": "", "en": ""}, "room_amenities": []}],  // room_amenities keys from: '.$roomAmen.'
 "seo_title": {"mk": "", "en": ""}, "seo_description": {"mk": "", "en": ""}
}
Make one room per distinct room / unit type found in the source (the record itself and the other rooms of the same object). Merge duplicates.';
    }

    /* ---------------------------------------------------------------- saving */

    protected static function loc($v, string $kind = 'text'): ?array
    {
        if (! is_array($v)) {
            return filled($v) ? ['mk' => static::clean($v, $kind)] : null;
        }
        $out = [];
        foreach (['mk', 'en'] as $l) {
            $x = $v[$l] ?? null;
            if (is_array($x)) {
                $x = implode("\n", array_filter(array_map(fn ($i) => trim(ltrim((string) $i, "-•* \t")), $x)));
            }
            if (filled($x)) {
                $out[$l] = static::clean($x, $kind);
            }
        }

        return $out ?: null;
    }

    protected static function clean($v, string $kind): string
    {
        return $kind === 'html' ? trim(strip_tags((string) $v, '<p><ul><ol><li><strong><b><em><i><h3><h4><br>')) : trim(strip_tags((string) $v));
    }

    protected static function int($v, int $min = 0, int $max = 100000): ?int
    {
        return is_numeric($v) && (int) $v >= $min && (int) $v <= $max ? (int) $v : null;
    }

    protected static function str($v, int $len = 190): ?string
    {
        return is_scalar($v) && filled($v) ? Str::limit(trim(strip_tags((string) $v)), $len, '') : null;
    }

    /** Main photo: the supplier's main image, else the first one */
    protected static function cover(TravelObject $o, $record): void
    {
        $record->refresh();
        if (! $record->cover_image && ($path = ($o->cover_image ?: $o->images->sortByDesc('is_main')->first()?->path))) {
            $record->forceFill(['cover_image' => $path])->saveQuietly();
        }
    }

    protected static function makeTour(TravelObject $o, array $a): array
    {
        $title = static::loc($a['title'] ?? null) ?? ['mk' => trim($o->name)];
        $data = [
            'agency_id' => $o->agency_id, 'tour_operator_id' => $o->tour_operator_id, 'group_id' => $o->group_id, 'destination_id' => $o->destination_id,
            'title' => $title,
            'subtitle' => static::loc($a['subtitle'] ?? null),
            'tour_type' => TourType::tryFrom((string) ($a['tour_type'] ?? '')) ?? TourType::Bus,
            'location' => static::str($a['location'] ?? null) ?? (trim(collect([$o->place, $o->region])->filter()->implode(', ')) ?: null),
            'country' => static::str($a['country'] ?? null, 100) ?? $o->country,
            'map_location' => $o->map_location,
            'duration_days' => static::int($a['duration_days'] ?? null, 1, 365),
            'duration_nights' => static::int($a['duration_nights'] ?? null, 0, 365),
            'is_flight' => (bool) ($a['is_flight'] ?? false),
            'has_guide' => (bool) ($a['has_guide'] ?? false),
            'short_description' => static::loc($a['short_description'] ?? null),
            'description' => static::loc($a['description'] ?? null, 'html'),
            'highlights' => static::loc($a['highlights'] ?? null),
            'included' => static::loc($a['included'] ?? null),
            'not_included' => static::loc($a['not_included'] ?? null),
            'important_info' => static::loc($a['important_info'] ?? null, 'html'),
            'payment_terms' => static::loc($a['payment_terms'] ?? null),
            'cancellation_policy' => static::loc($a['cancellation_policy'] ?? null),
            'baggage_info' => static::loc($a['baggage_info'] ?? null),
            'notes' => static::loc($a['notes'] ?? null, 'html'),
            'seo_title' => static::loc($a['seo_title'] ?? null),
            'seo_description' => static::loc($a['seo_description'] ?? null),
            'is_active' => false,
        ];
        $tour = Tour::create($data);
        $days = 0;
        foreach (array_values((array) ($a['days'] ?? [])) as $i => $d) {
            if (! is_array($d) || ! ($t = static::loc($d['title'] ?? null))) {
                continue;
            }
            $tour->days()->create([
                'day_number' => static::int($d['day'] ?? null, 1, 365) ?? $i + 1,
                'title' => $t, 'description' => static::loc($d['description'] ?? null, 'html'),
                'meals' => static::str($d['meals'] ?? null, 50), 'overnight' => static::str($d['overnight'] ?? null, 100),
            ]);
            $days++;
        }
        AriesConverter::copyImages($o, $tour);
        static::cover($o, $tour);

        return ['url' => TourResource::getUrl('edit', ['record' => $tour]), 'record' => $tour,
            'filled' => count(array_filter($data, fn ($v) => $v !== null && $v !== [] && $v !== false)) - 2, 'days' => $days, 'rooms' => 0];
    }

    protected static function makeHotel(TravelObject $o, array $a): array
    {
        $boards = array_values(array_filter(array_map(fn ($b) => Board::tryFrom(strtoupper((string) $b))?->value, (array) ($a['board_types'] ?? []))));
        $data = [
            'agency_id' => $o->agency_id, 'tour_operator_id' => $o->tour_operator_id, 'group_id' => $o->group_id, 'destination_id' => $o->destination_id,
            'name' => static::str($a['name'] ?? null) ?? trim($o->name),
            'type' => AccommodationType::tryFrom((string) ($a['type'] ?? ''))?->value ?? ($o->type === ObjectType::Apartment ? AccommodationType::Apartment->value : AccommodationType::Hotel->value),
            'stars' => static::int($a['stars'] ?? null, 1, 5) ?? $o->stars,
            'address' => static::str($a['address'] ?? null),
            'place' => static::str($a['place'] ?? null) ?? $o->place,
            'region' => static::str($a['region'] ?? null) ?? $o->region,
            'country' => static::str($a['country'] ?? null, 100) ?? $o->country,
            'map_location' => $o->map_location,
            'short_description' => static::loc($a['short_description'] ?? null),
            'overview' => static::loc($a['overview'] ?? null, 'html'),
            'house_rules' => static::loc($a['house_rules'] ?? null, 'html'),
            'notes' => static::loc($a['notes'] ?? null, 'html'),
            'cancellation_policy' => static::loc($a['cancellation_policy'] ?? null),
            'check_in_time' => preg_match('/^\d{1,2}[:.]\d{2}$/', (string) ($a['check_in_time'] ?? '')) ? str_replace('.', ':', $a['check_in_time']) : null,
            'check_out_time' => preg_match('/^\d{1,2}[:.]\d{2}$/', (string) ($a['check_out_time'] ?? '')) ? str_replace('.', ':', $a['check_out_time']) : null,
            'distance_beach_m' => static::int($a['distance_beach_m'] ?? null, 0, 500000),
            'distance_center_m' => static::int($a['distance_center_m'] ?? null, 0, 500000),
            'distance_ski_lift_m' => static::int($a['distance_ski_lift_m'] ?? null, 0, 500000),
            'distance_airport_km' => is_numeric($a['distance_airport_km'] ?? null) && $a['distance_airport_km'] >= 0 && $a['distance_airport_km'] < 10000 ? round((float) $a['distance_airport_km'], 1) : null,
            'board_types' => $boards ?: ($o->board && ($b = Board::tryFrom(strtoupper($o->board))) ? [$b->value] : null),
            'seo_title' => static::loc($a['seo_title'] ?? null),
            'seo_description' => static::loc($a['seo_description'] ?? null),
            'is_active' => false,
        ];
        $acc = Accommodation::create($data);

        $keys = array_values(array_filter((array) ($a['amenities'] ?? []), 'is_string'));
        if ($keys) {
            $acc->amenities()->sync(Amenity::query()->whereIn('key', $keys)->pluck('id'));
        }
        $roomKeys = Amenity::query()->pluck('key')->all();
        $rooms = 0;
        foreach (array_values((array) ($a['rooms'] ?? [])) as $i => $r) {
            if (! is_array($r) || ! ($name = static::loc($r['name'] ?? null))) {
                continue;
            }
            $acc->rooms()->create([
                'name' => $name,
                'room_type' => RoomType::tryFrom((string) ($r['room_type'] ?? ''))?->value ?? RoomType::Double->value,
                'board' => Board::tryFrom(strtoupper((string) ($r['board'] ?? '')))?->value ?? ($boards[0] ?? Board::Bb->value),
                'max_adults' => static::int($r['max_adults'] ?? null, 1, 20) ?? 2,
                'max_children' => static::int($r['max_children'] ?? null, 0, 10) ?? 0,
                'beds' => static::str($r['beds'] ?? null),
                'size_m2' => static::int($r['size_m2'] ?? null, 5, 2000),
                'description' => static::loc($r['description'] ?? null),
                'room_amenities' => array_values(array_intersect((array) ($r['room_amenities'] ?? []), $roomKeys)) ?: null,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
            $rooms++;
        }
        AriesConverter::copyImages($o, $acc);
        static::cover($o, $acc);

        return ['url' => AccommodationResource::getUrl('edit', ['record' => $acc]), 'record' => $acc,
            'filled' => count(array_filter($data, fn ($v) => $v !== null && $v !== [] && $v !== false)) - 2 + ($keys ? 1 : 0), 'days' => 0, 'rooms' => $rooms];
    }
}
