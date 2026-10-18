<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Charter;
use App\Models\Event;
use App\Models\OfferCost;
use App\Models\Tour;
use App\Models\VisaType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Financial + availability report of ONE offer (tour, hotel, event, charter, visa).
 *
 * Revenue  = totals of the bookings in scope (not cancelled; inquiries only on request)
 * Cost     = every cost line × the numbers from those bookings (passengers, nights, rooms…)
 * Profit   = revenue − cost
 * Clients  = paid in full / partly / not at all, and what they still owe
 */
class OfferReport
{
    public const TYPES = ['tour' => Tour::class, 'accommodation' => Accommodation::class, 'event' => Event::class, 'charter' => Charter::class, 'visa_type' => VisaType::class];

    /** Statuses counted by default (inquiries are not sales yet, cancelled are shown separately) */
    public const DEFAULT_STATUSES = ['pending', 'confirmed', 'completed'];

    public Collection $bookings;     // in scope, not cancelled

    public Collection $cancelled;    // cancelled bookings in scope

    /**
     * @param  Collection|null  $preloaded  all bookings of this offer (with departure, room, ticket, bookable, payments) – saves queries in period reports
     */
    public function __construct(public Model $offer, public array $filters = [], protected ?Collection $preloaded = null)
    {
        $this->filters += ['departure_id' => null, 'from' => null, 'to' => null, 'statuses' => self::DEFAULT_STATUSES, 'basis' => 'travel'];
        $this->offer->loadMissing(['costs.departure', 'costs.tourOperator']);
        $this->load();
    }

    /** Season of a leased / owned property (defaults to the current year) */
    public static function leaseWindow(Model $o, array $extra = []): array
    {
        $from = $o->getAttribute('lease_from') ?: now()->startOfYear();
        $to = $o->getAttribute('lease_to') ?: now()->endOfYear();

        return $extra + ['from' => Carbon::parse($from)->toDateString(), 'to' => Carbon::parse($to)->toDateString()];
    }

    public function isLeased(): bool
    {
        return $this->offer instanceof Accommodation && in_array($this->offer->getAttribute('ownership'), ['lease', 'own'], true);
    }

    /**
     * Break-even counter for leased / owned properties:
     * total costs of the season minus every payment received – what is left, and about how many bookings that is.
     */
    public function breakEven(): array
    {
        $lines = $this->costLines();
        $t = $this->totals($lines);
        $cost = $t['cost'];
        $collected = $t['collected'];
        $avg = $t['bookings'] ? $t['revenue'] / $t['bookings'] : null;
        if (! $avg && $this->offer instanceof Accommodation) {
            // no bookings yet: estimate from the room prices × a week
            $p = (float) $this->offer->rooms()->where('is_active', true)->avg('price');
            $avg = $p > 0 ? $p * 7 : null;
        }
        $left = max(0, round($cost - $collected, 2));
        $leftBooked = max(0, round($cost - $t['revenue'], 2));

        return [
            'currency' => $this->currency(), 'from' => $this->filters['from'], 'to' => $this->filters['to'],
            'cost' => $cost, 'collected' => $collected, 'revenue' => $t['revenue'], 'owed' => $t['owed'],
            'left' => $left, 'left_booked' => $leftBooked,
            'covered_pct' => $cost > 0 ? min(100, round($collected / $cost * 100, 1)) : 100,
            'booked_pct' => $cost > 0 ? min(100, round($t['revenue'] / $cost * 100, 1)) : 100,
            'profit_now' => round($collected - $cost, 2), 'profit_expected' => round($t['revenue'] - $cost, 2),
            'avg_booking' => $avg ? round($avg, 2) : null,
            'bookings_needed' => $avg ? (int) ceil($leftBooked / $avg) : null,
            'bookings' => $t['bookings'], 'paid_count' => $t['paid_count'],
            'lines' => collect($lines)->groupBy('category')->map(fn ($g) => round($g->sum('total'), 2))->sortDesc()->all(),
        ];
    }

    /* ------------------------------------------------------------------ helpers */

    public static function typeLabels(): array
    {
        return ['tour' => __('Organized tours'), 'accommodation' => __('Hotels & apartments'), 'event' => __('Events'), 'charter' => __('Charters'), 'visa_type' => __('Visa services')];
    }

    public static function money($value, ?string $currency = 'EUR'): string
    {
        $v = (float) $value;
        $s = number_format(abs($v), 2, ',', '.');
        $s = str_ends_with($s, ',00') ? substr($s, 0, -3) : $s;
        $sym = ['EUR' => '€', 'USD' => '$', 'MKD' => 'ден.'][$currency ?: 'EUR'] ?? $currency;

        return ($v < 0 ? '−' : '').$s.' '.$sym;
    }

    public static function find(?string $key): ?Model
    {
        if (! $key || ! str_contains($key, ':')) {
            return null;
        }
        [$type, $id] = explode(':', $key, 2);
        $class = self::TYPES[$type] ?? null;

        return $class ? $class::query()->find((int) $id) : null;
    }

    public static function title(Model $m): string
    {
        foreach (['title', 'name'] as $f) {
            $v = $m->getAttribute($f);
            if (is_array($v)) {
                return (string) ($v['mk'] ?? $v['en'] ?? reset($v));
            }
            if (filled($v)) {
                return (string) $v;
            }
        }
        if ($m instanceof Charter) {
            return trim($m->region.' '.$m->departure_date?->format('d.m.Y'));
        }

        return '#'.$m->getKey();
    }

    public function currency(): string
    {
        return $this->offer->getAttribute('currency') ?: ($this->bookings->first()?->currency ?: 'EUR');
    }

    /** Currencies that differ from the report currency (amounts are not converted) */
    public function mixedCurrencies(): array
    {
        $c = $this->currency();

        return $this->bookings->pluck('currency')->merge($this->offer->costs->where('basis', '!=', 'percent_revenue')->pluck('currency'))
            ->filter()->unique()->reject(fn ($x) => $x === $c)->values()->all();
    }

    /** Travel date of a booking: booking dates, else the departure / event / charter date */
    public static function travelDate(Booking $b): ?Carbon
    {
        $d = $b->date_from ?? $b->departure?->start_date;
        if (! $d && $b->bookable instanceof Event) {
            $d = $b->bookable->starts_at;
        }
        if (! $d && $b->bookable instanceof Charter) {
            $d = $b->bookable->departure_date;
        }

        return $d ? Carbon::parse($d)->startOfDay() : null;
    }

    public function nightsOf(Booking $b): int
    {
        $n = $b->nights();
        if (! $n && $b->departure?->start_date && $b->departure?->end_date) {
            $n = (int) $b->departure->start_date->diffInDays($b->departure->end_date);
        }
        if (! $n && $this->offer instanceof Tour) {
            $n = (int) $this->offer->duration_nights;
        }

        return max(0, $n);
    }

    /* ------------------------------------------------------------------ data */

    protected function load(): void
    {
        $f = $this->filters;
        $all = $this->preloaded !== null
            ? $this->preloaded->when($f['departure_id'], fn ($c, $d) => $c->where('tour_departure_id', (int) $d))
            : Booking::withoutGlobalScopes()
                ->where('bookable_type', $this->offer->getMorphClass())->where('bookable_id', $this->offer->getKey())
                ->when($f['departure_id'], fn ($q, $d) => $q->where('tour_departure_id', $d))
                ->with(['departure', 'room', 'ticket', 'bookable', 'payments', 'assignedUser'])
                ->orderBy('customer_name')->get();

        $from = $f['from'] ? Carbon::parse($f['from'])->startOfDay() : null;
        $to = $f['to'] ? Carbon::parse($f['to'])->endOfDay() : null;
        $all = $all->filter(function (Booking $b) use ($from, $to) {
            if (! $from && ! $to) {
                return true;
            }
            $d = $this->filters['basis'] === 'booked' ? $b->created_at : (self::travelDate($b) ?? $b->created_at);

            return (! $from || $d >= $from) && (! $to || $d <= $to);
        });

        $statuses = array_values((array) $f['statuses']);
        $this->cancelled = $all->filter(fn (Booking $b) => $b->status === BookingStatus::Cancelled)->values();
        $this->bookings = $all->filter(fn (Booking $b) => $b->status !== BookingStatus::Cancelled && in_array($b->status?->value, $statuses, true))->values();
    }

    /** Bookings a cost line applies to (its departure, room and season) */
    protected function bookingsFor(OfferCost $c): Collection
    {
        return $this->bookings->filter(function (Booking $b) use ($c) {
            if ($c->tour_departure_id && (int) $b->tour_departure_id !== (int) $c->tour_departure_id) {
                return false;
            }
            if ($c->accommodation_room_id && (int) $b->accommodation_room_id !== (int) $c->accommodation_room_id) {
                return false;
            }
            if ($c->valid_from || $c->valid_to) {
                $d = self::travelDate($b);
                if (! $d || ($c->valid_from && $d->lt($c->valid_from)) || ($c->valid_to && $d->gt($c->valid_to))) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Share of a once-only line that belongs to the report (0…1):
     *  - a departure's line counts when that departure is in the period
     *  - a line with a validity period (lease for the season) is spread over its days, so a month gets its part
     *  - other lines count once, in the period of the offer's first travel date
     */
    protected function onceFactor(OfferCost $c): float
    {
        $f = $this->filters;
        if (! $this->onceApplies($c)) {
            return 0.0;
        }
        if (! $f['from'] && ! $f['to']) {
            return 1.0;
        }
        $from = $f['from'] ? Carbon::parse($f['from'])->startOfDay() : null;
        $to = $f['to'] ? Carbon::parse($f['to'])->startOfDay() : null;
        if ($c->tour_departure_id) {
            return 1.0;
        }
        if ($c->valid_from && $c->valid_to) {
            $days = (int) $c->valid_from->diffInDays($c->valid_to) + 1;
            $s = $from ? $c->valid_from->copy()->max($from) : $c->valid_from->copy();
            $e = $to ? $c->valid_to->copy()->min($to) : $c->valid_to->copy();

            return $e->lt($s) ? 0.0 : min(1.0, ((int) $s->diffInDays($e) + 1) / max(1, $days));
        }
        $anchor = $c->valid_from ?? $this->anchorDate() ?? $c->created_at;
        $anchor = Carbon::parse($anchor)->startOfDay();

        return ((! $from || $anchor->gte($from)) && (! $to || $anchor->lte($to))) ? 1.0 : 0.0;
    }

    /** First travel date of the offer (for placing once-only costs in a period) */
    protected ?Carbon $anchor = null;

    protected function anchorDate(): ?Carbon
    {
        if ($this->anchor) {
            return $this->anchor;
        }
        $o = $this->offer;
        $d = match (true) {
            $o instanceof Event => $o->starts_at,
            $o instanceof Charter => $o->departure_date,
            $o instanceof Tour => $o->departures()->min('start_date'),
            $o instanceof Accommodation => $o->getAttribute('lease_from'),
            default => null,
        };
        if (! $d) {
            $src = $this->preloaded ?? Booking::withoutGlobalScopes()->where('bookable_type', $o->getMorphClass())->where('bookable_id', $o->getKey())->with('departure')->get();
            $d = $src->map(fn (Booking $b) => self::travelDate($b))->filter()->min();
        }

        return $this->anchor = $d ? Carbon::parse($d) : null;
    }

    /** Is a once-only line inside the report (departure filter / period)? */
    protected function onceApplies(OfferCost $c): bool
    {
        $f = $this->filters;
        if ($f['departure_id'] && $c->tour_departure_id && (int) $c->tour_departure_id !== (int) $f['departure_id']) {
            return false;
        }
        if ($c->tour_departure_id) {
            $d = $c->departure?->start_date;
            if ($d && (($f['from'] && $d->lt(Carbon::parse($f['from']))) || ($f['to'] && $d->gt(Carbon::parse($f['to']))))) {
                return false;
            }
        }
        if (($f['from'] || $f['to']) && ($c->valid_from || $c->valid_to)) {
            if (($f['to'] && $c->valid_from && $c->valid_from->gt(Carbon::parse($f['to']))) || ($f['from'] && $c->valid_to && $c->valid_to->lt(Carbon::parse($f['from'])))) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, array{name:string, category:string, basis:string, units:float, unit_label:string, rate:float, quantity:float, total:float, paid:float, owed:float, currency:string, supplier:?string, due:?string}> */
    public function costLines(): array
    {
        $out = [];
        foreach ($this->offer->costs as $c) {
            $bs = $this->bookingsFor($c);
            $units = 0.0;
            $label = '';
            switch ($c->basis) {
                case 'fixed':
                    $units = $this->onceFactor($c); $label = $units > 0 && $units < 1 ? __('share of period') : __('once'); break;
                case 'per_departure':
                    $units = $bs->map(fn (Booking $b) => $b->tour_departure_id ? 'd'.$b->tour_departure_id : 't'.self::travelDate($b)?->toDateString())->unique()->count();
                    $label = __('departures'); break;
                case 'per_booking':
                    $units = $bs->count(); $label = __('bookings'); break;
                case 'per_person':
                    $units = $bs->sum(fn (Booking $b) => $b->passengerCount()); $label = __('passengers'); break;
                case 'per_adult':
                    $units = $bs->sum(fn (Booking $b) => (int) $b->adults); $label = __('adults'); break;
                case 'per_child':
                    $units = $bs->sum(fn (Booking $b) => (int) $b->children); $label = __('children'); break;
                case 'per_person_night':
                    $units = $bs->sum(fn (Booking $b) => $b->passengerCount() * $this->nightsOf($b)); $label = __('passenger nights'); break;
                case 'per_room':
                    $units = $bs->sum(fn (Booking $b) => max(1, (int) $b->quantity)); $label = __('rooms'); break;
                case 'per_room_night':
                    $units = $bs->sum(fn (Booking $b) => max(1, (int) $b->quantity) * $this->nightsOf($b)); $label = __('room nights'); break;
                case 'percent_revenue':
                    $units = (float) $bs->sum(fn (Booking $b) => (float) $b->total); $label = __('of sales'); break;
            }
            $qty = (float) $c->quantity ?: 1.0;
            $total = $c->basis === 'percent_revenue'
                ? round($units * (float) $c->amount / 100, 2)
                : round($units * (float) $c->amount * $qty, 2);
            $paid = (float) $c->paid_to_supplier;
            $out[] = [
                'id' => $c->id, 'name' => $c->name, 'category' => OfferCost::categories()[$c->category] ?? $c->category,
                'basis' => OfferCost::bases()[$c->basis] ?? $c->basis, 'basis_key' => $c->basis,
                'units' => $units, 'unit_label' => $label, 'rate' => (float) $c->amount, 'quantity' => $qty, 'total' => $total,
                'paid' => $paid, 'owed' => max(0, round($total - $paid, 2)),
                'currency' => $c->basis === 'percent_revenue' ? $this->currency() : ($c->currency ?: 'EUR'),
                'supplier' => $c->tourOperator?->name ?? $c->supplier, 'due' => $c->due_date?->format('d.m.Y'),
                'scope' => trim(implode(' · ', array_filter([
                    $c->departure?->start_date ? __('Departure').' '.$c->departure->start_date->format('d.m.Y') : null,
                    ($c->valid_from || $c->valid_to) ? ($c->valid_from?->format('d.m.Y') ?? '…').' – '.($c->valid_to?->format('d.m.Y') ?? '…') : null,
                ]))),
            ];
        }

        // events: organizer price of every ticket sold
        if ($this->offer instanceof Event) {
            foreach ($this->offer->tickets as $t) {
                if ($t->cost_price === null) {
                    continue;
                }
                $qty = (int) $this->bookings->where('event_ticket_id', $t->id)->sum(fn (Booking $b) => max(1, (int) $b->quantity));
                $total = round($qty * (float) $t->cost_price, 2);
                $tname = is_array($t->name) ? ($t->name['mk'] ?? reset($t->name)) : $t->name;
                $out[] = ['id' => null, 'name' => __('Tickets from the organizer').': '.$tname, 'category' => OfferCost::categories()['tickets'], 'basis' => __('Organizer price per ticket'),
                    'basis_key' => 'ticket', 'units' => $qty, 'unit_label' => __('tickets'), 'rate' => (float) $t->cost_price, 'quantity' => 1.0, 'total' => $total,
                    'paid' => 0.0, 'owed' => $total, 'currency' => $this->offer->currency ?: 'EUR', 'supplier' => $this->offer->getAttribute('organizer'), 'due' => null,
                    'scope' => __('sale price').' '.self::money($t->price, $this->offer->currency).' · '.__('profit per ticket').' '.self::money((float) $t->price - (float) $t->cost_price, $this->offer->currency)];
            }
        }

        return $out;
    }

    /** Payment state of a booking for the report */
    public static function payState(Booking $b): string
    {
        $paid = (float) $b->paid_amount;
        $total = (float) $b->total;
        if ($b->payment_status === PaymentStatus::Refunded) {
            return 'refunded';
        }
        if ($paid <= 0) {
            return 'unpaid';
        }
        if ($total > 0 && $paid + 0.009 < $total) {
            return 'partial';
        }

        return $total > 0 && $paid > $total + 0.009 ? 'overpaid' : 'paid';
    }

    public static function payStateLabels(): array
    {
        return ['paid' => __('Paid in full'), 'partial' => __('Partly paid'), 'unpaid' => __('Not paid'), 'overpaid' => __('Overpaid'), 'refunded' => __('Refunded')];
    }

    /** One row per booking, with its passengers */
    public function clients(): array
    {
        return $this->bookings->map(function (Booking $b) {
            $names = collect((array) $b->passengers)->map(fn ($p) => trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')))->filter()->values()->all();
            $state = self::payState($b);
            $last = $b->payments->last();

            return [
                'id' => $b->id, 'reference' => $b->reference, 'customer' => $b->customer_name, 'phone' => $b->customer_phone, 'email' => $b->customer_email,
                'passengers' => $names, 'adults' => (int) $b->adults, 'children' => (int) $b->children, 'pax' => $b->passengerCount(),
                'date' => self::travelDate($b)?->format('d.m.Y'), 'nights' => $this->nightsOf($b),
                'room' => $b->room ? (is_array($b->room->name) ? ($b->room->name['mk'] ?? reset($b->room->name)) : $b->room->name) : ($b->ticket ? (is_array($b->ticket->name) ? ($b->ticket->name['mk'] ?? '') : $b->ticket->name) : null),
                'status' => $b->status?->getLabel() ?? (string) $b->status?->value,
                'total' => (float) $b->total, 'paid' => (float) $b->paid_amount, 'owed' => $b->balance(), 'over' => max(0, round((float) $b->paid_amount - (float) $b->total, 2)),
                'state' => $state, 'payments' => $b->payments->count(), 'last_payment' => $last?->paid_at?->format('d.m.Y'),
                'currency' => $b->currency ?: 'EUR',
            ];
        })->all();
    }

    /** Capacity / sold / free for the offer in the report scope */
    public function availability(): array
    {
        $o = $this->offer;
        $f = $this->filters;
        $from = $f['from'] ? Carbon::parse($f['from']) : null;
        $to = $f['to'] ? Carbon::parse($f['to']) : null;
        $rows = [];

        if ($o instanceof Tour) {
            $deps = $o->departures()->orderBy('start_date')
                ->when($f['departure_id'], fn ($q, $d) => $q->whereKey($d))
                ->when($from, fn ($q) => $q->whereDate('start_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))->get();
            foreach ($deps as $d) {
                $ours = $this->bookings->where('tour_departure_id', $d->id)->sum(fn (Booking $b) => $b->passengerCount());
                $sold = max((int) $d->seats_booked, $ours);
                $cap = $d->seats_total;
                $rows[] = ['label' => $d->start_date?->format('d.m.Y').($d->end_date ? ' – '.$d->end_date->format('d.m.Y') : ''), 'capacity' => $cap, 'sold' => $sold, 'ours' => $ours,
                    'free' => $cap === null ? null : max(0, $cap - $sold), 'occupancy' => $cap ? round($sold / $cap * 100) : null, 'unit' => __('seats')];
            }

            return ['kind' => 'departures', 'rows' => $rows];
        }

        if ($o instanceof Accommodation) {
            // room nights in the period: capacity = units × nights, sold = our bookings overlapping the period
            $bs = $this->bookings->filter(fn (Booking $b) => $b->date_from && $b->date_to);
            $start = $from ?? $bs->min('date_from');
            $end = $to ? $to->copy()->addDay() : $bs->max('date_to');
            if (! $start || ! $end || $end->lte($start)) {
                return ['kind' => 'rooms', 'rows' => [], 'period' => null];
            }
            $start = Carbon::parse($start)->startOfDay();
            $end = Carbon::parse($end)->startOfDay();
            $nights = (int) $start->diffInDays($end);
            $rooms = $o->rooms()->orderBy('sort_order')->get();
            foreach ($rooms as $room) {
                $units = max(1, (int) ($room->units ?: 1));
                $sold = 0;
                // a single-unit property (villa): bookings without a room are for that unit
                foreach ($bs->filter(fn (Booking $b) => (int) $b->accommodation_room_id === (int) $room->id || ($rooms->count() === 1 && ! $b->accommodation_room_id)) as $b) {
                    $s = $b->date_from->copy()->max($start);
                    $e = $b->date_to->copy()->min($end);
                    if ($e->gt($s)) {
                        $sold += (int) $s->diffInDays($e) * max(1, (int) $b->quantity);
                    }
                }
                $cap = $units * $nights;
                $rows[] = ['label' => is_array($room->name) ? ($room->name['mk'] ?? reset($room->name)) : $room->name, 'capacity' => $cap, 'sold' => $sold, 'ours' => $sold,
                    'free' => max(0, $cap - $sold), 'occupancy' => $cap ? round($sold / $cap * 100) : null, 'unit' => __('room nights'), 'units' => $units];
            }

            return ['kind' => 'rooms', 'rows' => $rows, 'period' => $start->format('d.m.Y').' – '.$end->copy()->subDay()->format('d.m.Y'), 'nights' => $nights];
        }

        if ($o instanceof Event) {
            foreach ($o->tickets()->orderBy('sort_order')->get() as $t) {
                $ours = $this->bookings->where('event_ticket_id', $t->id)->sum(fn (Booking $b) => max(1, (int) $b->quantity));
                $sold = max((int) $t->sold, $ours);
                $cap = $t->quantity;
                $rows[] = ['label' => is_array($t->name) ? ($t->name['mk'] ?? reset($t->name)) : $t->name, 'capacity' => $cap, 'sold' => $sold, 'ours' => $ours,
                    'free' => $cap === null ? null : max(0, $cap - $sold), 'occupancy' => $cap ? round($sold / $cap * 100) : null, 'unit' => __('tickets')];
            }

            return ['kind' => 'tickets', 'rows' => $rows];
        }

        if ($o instanceof Charter) {
            $sold = $this->bookings->sum(fn (Booking $b) => $b->passengerCount());
            $cap = (int) $o->seats ?: null;
            $rows[] = ['label' => self::title($o), 'capacity' => $cap, 'sold' => $sold, 'ours' => $sold, 'free' => $cap === null ? null : max(0, $cap - $sold), 'occupancy' => $cap ? round($sold / $cap * 100) : null, 'unit' => __('seats')];

            return ['kind' => 'seats', 'rows' => $rows];
        }

        return ['kind' => 'none', 'rows' => []];
    }

    /** Headline numbers */
    public function totals(?array $costLines = null): array
    {
        $costLines ??= $this->costLines();
        $b = $this->bookings;
        $revenue = round((float) $b->sum(fn ($x) => (float) $x->total), 2);
        $collected = round((float) $b->sum(fn ($x) => (float) $x->paid_amount), 2);
        $owed = round((float) $b->sum(fn (Booking $x) => $x->balance()), 2);
        $cost = round((float) array_sum(array_column($costLines, 'total')), 2);
        $costPaid = round((float) array_sum(array_column($costLines, 'paid')), 2);
        $states = $b->groupBy(fn (Booking $x) => self::payState($x))->map->count();
        $refundDue = round((float) $this->cancelled->filter(fn (Booking $x) => $x->payment_status !== PaymentStatus::Refunded)->sum(fn ($x) => max(0, (float) $x->paid_amount)), 2);

        return [
            'bookings' => $b->count(),
            'pax' => (int) $b->sum(fn (Booking $x) => $x->passengerCount()),
            'adults' => (int) $b->sum('adults'), 'children' => (int) $b->sum('children'),
            'revenue' => $revenue, 'collected' => $collected, 'owed' => $owed,
            'cost' => $cost, 'cost_paid' => $costPaid, 'cost_owed' => max(0, round($cost - $costPaid, 2)),
            'profit' => round($revenue - $cost, 2),
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
            'cash' => round($collected - $costPaid, 2),   // money in hand: collected from clients − paid to suppliers
            'paid_count' => (int) (($states['paid'] ?? 0) + ($states['overpaid'] ?? 0)), 'partial_count' => (int) ($states['partial'] ?? 0), 'unpaid_count' => (int) ($states['unpaid'] ?? 0),
            'cancelled' => $this->cancelled->count(), 'refund_due' => $refundDue,
            'per_pax_cost' => $b->count() ? round($cost / max(1, $b->sum(fn (Booking $x) => $x->passengerCount())), 2) : null,
        ];
    }

    public function editUrl(): ?string
    {
        $res = ['tour' => \App\Filament\Resources\TourResource::class, 'accommodation' => \App\Filament\Resources\AccommodationResource::class, 'event' => \App\Filament\Resources\EventResource::class,
            'charter' => \App\Filament\Resources\CharterResource::class, 'visa_type' => \App\Filament\Resources\VisaTypeResource::class][$this->offer->getMorphClass()] ?? null;
        try {
            return $res ? $res::getUrl('edit', ['record' => $this->offer]) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function key(): string
    {
        return $this->offer->getMorphClass().':'.$this->offer->getKey();
    }
}
