<?php

namespace App\Services\Ai;

use App\Models\Accommodation;
use App\Models\Event;
use App\Models\Tour;
use App\Models\VisaType;
use App\Services\Website\CatalogPresenter as P;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The agency's ACTIVE offer in a compact form for the chatbot. Only these items can ever be recommended:
 * tours with a future departure (or without dates), events that have not started, active hotels and visas.
 */
class ChatCatalog
{
    /** @return array<string, array> key "tour:5" => item */
    public static function items(int $agencyId): array
    {
        return Cache::remember("chat_catalog_{$agencyId}", now()->addMinutes(10), fn () => static::build($agencyId));
    }

    public static function forget(int $agencyId): void
    {
        Cache::forget("chat_catalog_{$agencyId}");
    }

    protected static function build(int $agencyId): array
    {
        $out = [];
        $today = today();
        $mk = fn ($v) => is_array($v) ? ($v['mk'] ?? $v['en'] ?? '') : (string) $v;

        foreach (Tour::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->with(['destination', 'group', 'departures'])->get() as $t) {
            $future = $t->departures->filter(fn ($d) => $d->start_date->gte($today));
            if ($t->departures->isNotEmpty() && $future->isEmpty()) {
                continue;   // all departures are in the past
            }
            $s = P::tourSummary($t);
            $dates = $future->sortBy('start_date')->take(6)->map(fn ($d) => $d->start_date->format('d.m.Y'))->implode(', ');
            $out["tour:{$t->id}"] = static::item($s, $mk($s['title']), implode(' | ', array_filter([
                'tour', $mk($s['tour_type']['label'] ?? null), $s['is_flight'] ? 'flight' : null,
                trim(($s['location'] ?? '').', '.($s['country'] ?? ''), ', '),
                $mk($s['destination']['name'] ?? null), $mk($s['group']['name'] ?? null),
                $s['duration']['days'] ? $s['duration']['days'].' days' : null,
                $dates ? 'departures: '.$dates : 'dates on request',
                $s['price_from'] ? 'from '.$s['price_from'].' '.$s['currency'] : null,
                ! empty($s['promotions']['last_minute']) ? 'LAST MINUTE' : null,
                Str::limit(strip_tags($mk($s['short_description'])), 160),
            ])), $future->first()?->start_date?->toDateString());
        }

        foreach (Event::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '>=', now()))->get() as $e) {
            $s = P::eventSummary($e);
            $out["event:{$e->id}"] = static::item($s, $mk($s['title']), implode(' | ', array_filter([
                'event / concert', $mk($s['category']['label'] ?? null), $s['performer'], trim(($s['venue'] ?? '').', '.($s['city'] ?? '').', '.($s['country'] ?? ''), ', '),
                $e->starts_at ? 'date: '.$e->starts_at->format('d.m.Y H:i') : null,
                $s['price_from'] ? 'from '.$s['price_from'].' '.$s['currency'] : null,
                Str::limit(strip_tags($mk($s['short_description'])), 140),
            ])), $e->starts_at?->toDateString());
        }

        foreach (Accommodation::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->with(['destination', 'amenities'])->get() as $a) {
            $s = P::accommodationSummary($a);
            $out["accommodation:{$a->id}"] = static::item($s, (string) $s['name'], implode(' | ', array_filter([
                'hotel / apartment', $mk($s['accommodation_type']['label'] ?? null), $s['stars'] ? $s['stars'].'*' : null,
                trim(($s['place'] ?? '').', '.($s['region'] ?? '').', '.($s['country'] ?? ''), ', '), $mk($s['destination']['name'] ?? null),
                $s['board_types'] ? 'board: '.implode('/', $s['board_types']) : null,
                $s['price_from'] ? 'from '.$s['price_from'].' '.$s['currency'] : null,
                collect($s['amenities'])->take(8)->map(fn ($m) => $mk($m['name']))->implode(', '),
                Str::limit(strip_tags($mk($s['short_description'])), 140),
            ])));
        }

        foreach (VisaType::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->get() as $v) {
            $s = P::visaSummary($v);
            $out["visa:{$v->id}"] = static::item($s, trim($s['country'].' – '.$mk($s['name'])), implode(' | ', array_filter([
                'visa service', $s['country'], $mk($s['category']['label'] ?? null), $s['price'] ? $s['price'].' '.$s['currency'] : null,
                Str::limit(strip_tags($mk($s['short_description'])), 120),
            ])));
        }

        return $out;
    }

    protected static function item(array $s, string $title, string $line, ?string $date = null): array
    {
        return [
            'type' => $s['type'], 'id' => $s['id'], 'slug' => $s['slug'], 'title' => $s['title'] ?? ['mk' => $title, 'en' => null],
            'name' => $title, 'line' => $line, 'image' => $s['image'] ?? null,
            'price' => $s['price_from'] ?? $s['price'] ?? null, 'currency' => $s['currency'] ?? 'EUR', 'date' => $date,
            'featured' => (bool) ($s['is_featured'] ?? false), 'last_minute' => ! empty($s['promotions']['last_minute']),
        ];
    }

    protected static function words(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, 5), array_filter($words, fn ($w) => mb_strlen($w) >= 3))));
    }

    /** The items that best match the question (simple keyword search, featured and last minute first) */
    public static function relevant(int $agencyId, string $query, int $limit = 40, ?array $types = null): array
    {
        $items = static::items($agencyId);
        if ($types) {
            $items = array_filter($items, fn ($i) => in_array($i['type'], $types, true));
        }
        $q = static::words($query);
        $scored = [];
        foreach ($items as $key => $i) {
            $w = static::words($i['line'].' '.$i['name']);
            $score = count(array_intersect($q, $w)) * 10 + ($i['featured'] ? 2 : 0) + ($i['last_minute'] ? 2 : 0) + ($i['date'] ? 1 : 0);
            $scored[$key] = $score;
        }
        arsort($scored);

        return array_intersect_key($items, array_slice($scored, 0, $limit, true));
    }

    /** Short overview of the whole offer (counts per type, destinations) */
    public static function overview(int $agencyId): string
    {
        $items = static::items($agencyId);
        $count = array_count_values(array_column($items, 'type'));

        return 'Active offer: '.collect($count)->map(fn ($n, $t) => "{$n} {$t}")->implode(', ').'.';
    }

    /** Card for the website (only active items) */
    public static function card(int $agencyId, string $key): ?array
    {
        $i = static::items($agencyId)[$key] ?? null;

        return $i ? ['type' => $i['type'], 'id' => $i['id'], 'slug' => $i['slug'], 'title' => $i['title'], 'image' => $i['image'], 'price' => $i['price'], 'currency' => $i['currency'], 'date' => $i['date']] : null;
    }
}
