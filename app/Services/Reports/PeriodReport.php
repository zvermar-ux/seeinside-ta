<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\OfferCost;
use App\Models\TourOperator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Whole agency over a year / month / week (or any range):
 * sales, costs, profit and cash per sub-period, plus breakdowns by offer type, employee (operator) and tour operator.
 */
class PeriodReport
{
    public Carbon $from;

    public Carbon $to;

    /** @var array<int, array{label:string, from:Carbon, to:Carbon}> */
    public array $buckets = [];

    /** offer key => [offer, bookings] */
    protected array $offers = [];

    public function __construct(public array $filters = [])
    {
        $this->filters += ['period' => 'year', 'year' => now()->year, 'month' => now()->month, 'week' => null, 'from' => null, 'to' => null,
            'basis' => 'travel', 'statuses' => OfferReport::DEFAULT_STATUSES, 'types' => []];
        $this->window();
        $this->collect();
    }

    protected function window(): void
    {
        $f = $this->filters;
        $y = (int) ($f['year'] ?: now()->year);
        switch ($f['period']) {
            case 'month':
                $this->from = Carbon::create($y, (int) ($f['month'] ?: 1), 1)->startOfDay();
                $this->to = $this->from->copy()->endOfMonth()->startOfDay();
                // weeks of the month (Mon–Sun, cut at the month edges)
                $s = $this->from->copy();
                while ($s->lte($this->to)) {
                    $e = $s->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()->min($this->to);
                    $this->buckets[] = ['label' => $s->format('d.m').' – '.$e->format('d.m'), 'from' => $s->copy(), 'to' => $e->copy()];
                    $s = $e->copy()->addDay();
                }
                break;
            case 'week':
                $d = $f['week'] ? Carbon::parse($f['week']) : now();
                $this->from = $d->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $this->to = $this->from->copy()->addDays(6);
                foreach (CarbonPeriod::create($this->from, $this->to) as $day) {
                    $this->buckets[] = ['label' => mb_convert_case($day->locale(app()->getLocale())->isoFormat('dd D.MM'), MB_CASE_TITLE), 'from' => $day->copy(), 'to' => $day->copy()];
                }
                break;
            case 'range':
                $this->from = Carbon::parse($f['from'] ?: now()->startOfMonth())->startOfDay();
                $this->to = Carbon::parse($f['to'] ?: now())->startOfDay()->max($this->from);
                $days = (int) $this->from->diffInDays($this->to);
                $step = $days > 120 ? 'month' : ($days > 31 ? 'week' : 'day');
                $s = $this->from->copy();
                while ($s->lte($this->to)) {
                    $e = match ($step) { 'month' => $s->copy()->endOfMonth(), 'week' => $s->copy()->endOfWeek(Carbon::SUNDAY), default => $s->copy() };
                    $e = $e->startOfDay()->min($this->to);
                    $this->buckets[] = ['label' => $step === 'month' ? mb_convert_case($s->locale(app()->getLocale())->isoFormat('MMM YYYY'), MB_CASE_TITLE) : ($step === 'week' ? $s->format('d.m').' – '.$e->format('d.m') : $s->format('d.m')), 'from' => $s->copy(), 'to' => $e->copy()];
                    $s = $e->copy()->addDay();
                }
                break;
            default: // year → months
                $this->from = Carbon::create($y, 1, 1)->startOfDay();
                $this->to = Carbon::create($y, 12, 31)->startOfDay();
                for ($mth = 1; $mth <= 12; $mth++) {
                    $s = Carbon::create($y, $mth, 1);
                    $this->buckets[] = ['label' => mb_convert_case($s->locale(app()->getLocale())->isoFormat('MMMM'), MB_CASE_TITLE), 'from' => $s->copy(), 'to' => $s->copy()->endOfMonth()->startOfDay()];
                }
        }
    }

    public function title(): string
    {
        return match ($this->filters['period']) {
            'month' => mb_convert_case($this->from->locale(app()->getLocale())->isoFormat('MMMM YYYY'), MB_CASE_TITLE),
            'week' => __('Week').' '.$this->from->isoWeek().' · '.$this->from->format('d.m').' – '.$this->to->format('d.m.Y'),
            'range' => $this->from->format('d.m.Y').' – '.$this->to->format('d.m.Y'),
            default => __('Year').' '.$this->from->year,
        };
    }

    /** Bookings in the window grouped per offer, plus offers whose costs fall in the window (e.g. a leased villa with no bookings yet) */
    protected function collect(): void
    {
        $types = array_values(array_filter((array) $this->filters['types'])) ?: array_keys(OfferReport::TYPES);
        $basis = $this->filters['basis'];
        $q = Booking::query()->whereIn('bookable_type', $types)->whereNotNull('bookable_id')
            ->with(['departure', 'room', 'ticket', 'bookable', 'payments', 'assignedUser']);
        if ($basis === 'booked') {
            $q->whereBetween('created_at', [$this->from, $this->to->copy()->endOfDay()]);
        } else {
            // travel date: booking dates or the departure date; a little wider SQL filter, exact filter in PHP
            $q->where(fn ($w) => $w->whereBetween('date_from', [$this->from->toDateString(), $this->to->toDateString()])
                ->orWhereHas('departure', fn ($d) => $d->whereBetween('start_date', [$this->from->toDateString(), $this->to->toDateString()]))
                ->orWhere(fn ($n) => $n->whereNull('date_from')->whereNull('tour_departure_id')));
        }
        foreach ($q->get() as $b) {
            if (! $b->bookable) {
                continue;
            }
            $key = $b->bookable_type.':'.$b->bookable_id;
            $this->offers[$key] ??= ['offer' => $b->bookable, 'bookings' => collect()];
            $this->offers[$key]['bookings']->push($b);
        }
        // offers with costs but no bookings in the window still carry their (prorated) costs
        $withCosts = OfferCost::query()->whereIn('costable_type', $types)
            ->select('costable_type', 'costable_id')->distinct()->get();
        foreach ($withCosts as $c) {
            $key = $c->costable_type.':'.$c->costable_id;
            if (! isset($this->offers[$key]) && ($o = OfferReport::find($key))) {
                $this->offers[$key] = ['offer' => $o, 'bookings' => collect()];
            }
        }
    }

    protected function reportFor(array $entry, Carbon $from, Carbon $to): OfferReport
    {
        return new OfferReport($entry['offer'], [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'statuses' => $this->filters['statuses'], 'basis' => $this->filters['basis'],
        ], $entry['bookings']);
    }

    /** Totals of a window over all offers; also returns the per-offer reports */
    protected function aggregate(Carbon $from, Carbon $to): array
    {
        $rows = [];
        $reports = [];
        foreach ($this->offers as $key => $entry) {
            $r = $this->reportFor($entry, $from, $to);
            $t = $r->totals();
            if (! $t['bookings'] && ! $t['cost'] && ! $t['cancelled']) {
                continue;
            }
            $reports[$key] = $r;
            $rows[$key] = $t + ['key' => $key, 'title' => OfferReport::title($r->offer), 'type' => $r->offer->getMorphClass(), 'currency' => $r->currency(),
                'tour_operator_id' => $r->offer->getAttribute('tour_operator_id')];
        }

        return [GroupReport::sum(array_values($rows)), $rows, $reports];
    }

    /** Money received (and refunded) by payment date – the real cash flow */
    protected function payments(Carbon $from, Carbon $to): array
    {
        $types = array_values(array_filter((array) $this->filters['types'])) ?: array_keys(OfferReport::TYPES);
        $q = BookingPayment::query()->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->whereHas('booking', fn ($b) => $b->whereIn('bookable_type', $types));

        return ['in' => round((float) (clone $q)->where('amount', '>', 0)->sum('amount'), 2), 'out' => round((float) -(clone $q)->where('amount', '<', 0)->sum('amount'), 2)];
    }

    public function build(): array
    {
        [$total, $rows, $reports] = $this->aggregate($this->from, $this->to);
        $pay = $this->payments($this->from, $this->to);

        $series = [];
        foreach ($this->buckets as $b) {
            [$t] = $this->aggregate($b['from'], $b['to']);
            $series[] = ['label' => $b['label'], 'from' => $b['from']->toDateString(), 'to' => $b['to']->toDateString()] + $t + ['pay' => $this->payments($b['from'], $b['to'])];
        }

        // by offer type
        $byType = [];
        foreach ($rows as $r) {
            $byType[$r['type']][] = $r;
        }
        $byType = collect($byType)->map(fn ($g, $type) => ['label' => OfferReport::typeLabels()[$type] ?? $type] + GroupReport::sum($g))->sortByDesc('revenue')->values()->all();

        // by tour operator (offers linked to one)
        $ops = TourOperator::query()->pluck('name', 'id');
        $byOperator = collect($rows)->groupBy(fn ($r) => $r['tour_operator_id'] ?: 0)
            ->map(fn ($g, $id) => ['label' => $id ? ($ops[$id] ?? '#'.$id) : __('Own offer / no tour operator')] + GroupReport::sum($g->values()->all()))
            ->sortByDesc('revenue')->values()->all();

        // by employee (who handled the booking); the offer's cost is shared by the booking's part of the offer's sales
        $emp = [];
        foreach ($reports as $key => $r) {
            $t = $rows[$key];
            $ratio = $t['revenue'] > 0 ? $t['cost'] / $t['revenue'] : 0;
            foreach ($r->bookings as $b) {
                $id = $b->assigned_user_id ?: 0;
                $e = &$emp[$id];
                $e ??= ['label' => $b->assignedUser?->name ?? __('Not assigned (website)'), 'bookings' => 0, 'pax' => 0, 'revenue' => 0, 'collected' => 0, 'owed' => 0, 'cost' => 0];
                $e['bookings']++;
                $e['pax'] += $b->passengerCount();
                $e['revenue'] += (float) $b->total;
                $e['collected'] += (float) $b->paid_amount;
                $e['owed'] += $b->balance();
                $e['cost'] += (float) $b->total * $ratio;
                unset($e);
            }
        }
        $byEmployee = collect($emp)->map(function ($e) {
            foreach (['revenue', 'collected', 'owed', 'cost'] as $k) {
                $e[$k] = round($e[$k], 2);
            }
            $e['profit'] = round($e['revenue'] - $e['cost'], 2);
            $e['margin'] = $e['revenue'] > 0 ? round($e['profit'] / $e['revenue'] * 100, 1) : null;

            return $e;
        })->sortByDesc('revenue')->values()->all();

        $top = collect($rows)->sortByDesc('profit')->values()->all();

        return compact('total', 'pay', 'series', 'byType', 'byOperator', 'byEmployee', 'top') + ['offers' => count($rows)];
    }

    /** Same period one year earlier, for comparison */
    public function previousYear(): array
    {
        $prev = new self(array_merge($this->filters, [
            'year' => $this->from->year - 1,
            'week' => $this->filters['period'] === 'week' ? $this->from->copy()->subYear()->toDateString() : $this->filters['week'],
            'from' => $this->filters['period'] === 'range' ? $this->from->copy()->subYear()->toDateString() : null,
            'to' => $this->filters['period'] === 'range' ? $this->to->copy()->subYear()->toDateString() : null,
        ]));
        [$t] = $prev->aggregate($prev->from, $prev->to);

        return $t + ['pay' => $prev->payments($prev->from, $prev->to)];
    }
}
