<?php

namespace App\Services\Reports;

use App\Models\Accommodation;
use App\Models\Charter;
use App\Models\Event;
use App\Models\Group;
use App\Models\Tour;
use App\Models\VisaType;
use Illuminate\Support\Collection;

/**
 * Report over many offers at once, e.g. "all villas on Lefkada" or "all ski tours in January":
 * one row per offer (sales, cost, profit, debts, occupancy) plus totals.
 */
class GroupReport
{
    /** @var Collection<int, OfferReport> */
    public Collection $reports;

    public function __construct(public array $filters = [])
    {
        $this->filters += ['types' => [], 'groups' => [], 'accommodation_types' => [], 'tour_types' => [], 'event_categories' => [], 'place' => null,
            'tour_operator_id' => null, 'from' => null, 'to' => null, 'statuses' => OfferReport::DEFAULT_STATUSES, 'only_with_bookings' => true];
        $scope = ['from' => $this->filters['from'], 'to' => $this->filters['to'], 'statuses' => $this->filters['statuses']];
        $this->reports = $this->offers()->map(fn ($o) => new OfferReport($o, $scope))
            ->filter(fn (OfferReport $r) => ! $this->filters['only_with_bookings'] || $r->bookings->isNotEmpty() || $r->cancelled->isNotEmpty())
            ->values();
    }

    /** Selected groups/destinations with all their sub-groups */
    protected function groupIds(): array
    {
        $ids = array_map('intval', (array) $this->filters['groups']);
        $all = $ids;
        $next = $ids;
        for ($i = 0; $i < 6 && $next; $i++) {
            $next = Group::query()->whereIn('parent_id', $next)->pluck('id')->all();
            $all = array_merge($all, $next);
        }

        return array_values(array_unique($all));
    }

    public function offers(): Collection
    {
        $f = $this->filters;
        $types = array_values(array_filter((array) $f['types'])) ?: array_keys(OfferReport::TYPES);
        // a filter that only exists for some types narrows the report to those types
        if ($f['accommodation_types']) {
            $types = array_intersect($types, ['accommodation']);
        }
        if ($f['tour_types']) {
            $types = array_intersect($types, ['tour']);
        }
        if ($f['event_categories']) {
            $types = array_intersect($types, ['event']);
        }
        $groups = $f['groups'] ? $this->groupIds() : null;
        $place = trim((string) $f['place']);
        $out = collect();

        if (in_array('tour', $types, true)) {
            $out = $out->merge(Tour::query()
                ->when($groups, fn ($q) => $q->where(fn ($w) => $w->whereIn('group_id', $groups)->orWhereIn('destination_id', $groups)))
                ->when($f['tour_types'], fn ($q, $t) => $q->whereIn('tour_type', $t))
                ->when($f['tour_operator_id'], fn ($q, $t) => $q->where('tour_operator_id', $t))
                ->get());
        }
        if (in_array('accommodation', $types, true)) {
            $out = $out->merge(Accommodation::query()
                ->when($groups, fn ($q) => $q->where(fn ($w) => $w->whereIn('group_id', $groups)->orWhereIn('destination_id', $groups)))
                ->when($f['accommodation_types'], fn ($q, $t) => $q->whereIn('type', $t))
                ->when($f['tour_operator_id'], fn ($q, $t) => $q->where('tour_operator_id', $t))
                ->get());
        }
        if (in_array('event', $types, true) && ! $f['tour_operator_id']) {
            $out = $out->merge(Event::query()
                ->when($groups, fn ($q) => $q->whereIn('destination_id', $groups))
                ->when($f['event_categories'], fn ($q, $t) => $q->whereIn('category', $t))
                ->get());
        }
        if (in_array('charter', $types, true) && ! $groups && ! $f['tour_operator_id']) {
            $out = $out->merge(Charter::query()
                ->get());
        }
        if (in_array('visa_type', $types, true) && ! $groups && ! $f['tour_operator_id']) {
            $out = $out->merge(VisaType::query()->get());
        }

        if ($place !== '') {
            // titles are stored as JSON (escaped), so the text search runs here
            $needle = mb_strtolower($place);
            $out = $out->filter(function ($o) use ($needle) {
                $hay = [OfferReport::title($o)];
                foreach (['place', 'location', 'city', 'venue', 'region', 'destination', 'country'] as $f) {
                    $v = $o->getAttribute($f);
                    $hay[] = is_array($v) ? implode(' ', $v) : (string) $v;
                }
                foreach (['group', 'destination'] as $rel) {
                    if (method_exists($o, $rel) && ($g = $o->{$rel}) instanceof Group) {
                        $hay[] = $g->display_name ?? '';
                    }
                }

                return str_contains(mb_strtolower(implode(' ', $hay)), $needle);
            });
        }

        return $out->values();
    }

    /** One row per offer */
    public function rows(): array
    {
        return $this->reports->map(function (OfferReport $r) {
            $t = $r->totals();
            $av = $r->availability();
            $cap = collect($av['rows'])->sum(fn ($x) => (int) ($x['capacity'] ?? 0));
            $sold = collect($av['rows'])->sum(fn ($x) => (int) ($x['sold'] ?? 0));

            return $t + [
                'key' => $r->key(), 'title' => OfferReport::title($r->offer), 'type' => OfferReport::typeLabels()[$r->offer->getMorphClass()] ?? '',
                'place' => $r->offer->getAttribute('place') ?? $r->offer->getAttribute('location') ?? $r->offer->getAttribute('city'),
                'currency' => $r->currency(), 'occupancy' => $cap > 0 ? round($sold / $cap * 100) : null, 'capacity' => $cap ?: null, 'sold' => $sold,
                'unit' => $av['rows'][0]['unit'] ?? null, 'mixed' => (bool) $r->mixedCurrencies(),
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    public static function sum(array $rows): array
    {
        $keys = ['bookings', 'pax', 'adults', 'children', 'revenue', 'collected', 'owed', 'cost', 'cost_paid', 'cost_owed', 'profit', 'cash', 'paid_count', 'partial_count', 'unpaid_count', 'cancelled', 'refund_due'];
        $t = [];
        foreach ($keys as $k) {
            $t[$k] = round(array_sum(array_column($rows, $k)), 2);
        }
        $t['margin'] = $t['revenue'] > 0 ? round($t['profit'] / $t['revenue'] * 100, 1) : null;
        $t['currency'] = collect($rows)->pluck('currency')->countBy()->sortDesc()->keys()->first() ?? 'EUR';
        $t['mixed'] = collect($rows)->pluck('currency')->unique()->count() > 1 || collect($rows)->contains('mixed', true);

        return $t;
    }
}
