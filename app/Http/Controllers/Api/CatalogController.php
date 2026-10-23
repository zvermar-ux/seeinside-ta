<?php

namespace App\Http\Controllers\Api;

use App\Enums\GroupType;
use App\Models\Accommodation;
use App\Models\Agency;
use App\Models\Airline;
use App\Models\Amenity;
use App\Models\DeletedRecord;
use App\Models\DeparturePlace;
use App\Models\Event;
use App\Models\Group;
use App\Models\Tour;
use App\Models\VisaType;
use App\Services\Website\CatalogPresenter as P;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class CatalogController extends Controller
{
    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    /** Base query for an agency's published records */
    protected function published(string $class, Request $request): Builder
    {
        return $class::query()->where('agency_id', $this->agencyId($request))->where('is_active', true);
    }

    protected function paginate(Builder $query, Request $request, callable $map): JsonResponse
    {
        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', 24))));

        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    protected function search(Builder $q, ?string $term, array $columns): Builder
    {
        if (filled($term)) {
            $q->where(function ($w) use ($term, $columns) {
                foreach ($columns as $c) {
                    $w->orWhere($c, 'like', '%'.$term.'%');
                }
            });
        }

        return $q;
    }

    /** Lists and settings the website needs for filters and forms */
    public function meta(Request $request): JsonResponse
    {
        $agency = Agency::find($this->agencyId($request));
        $groups = Group::withoutGlobalScopes()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('sort_order')->get();

        return response()->json([
            'agency' => [
                'name' => $agency->name, 'email' => $agency->email, 'phone' => $agency->phone, 'website' => $agency->website,
                'address' => $agency->address, 'city' => $agency->city, 'logo' => P::url($agency->logo), 'currency' => $agency->default_currency,
            ],
            'slider' => $this->sliderItems($agency->id),
            'flights' => ['search_url' => $agency->flight_search_url, 'widget_code' => $agency->flight_widget_code],
            'destinations' => $groups->where('type', GroupType::Destination)->map(fn ($g) => P::group($g) + ['parent_id' => $g->parent_id, 'image' => P::url($g->image)])->values(),
            'groups' => $groups->where('type', GroupType::Group)->map(fn ($g) => P::group($g) + ['parent_id' => $g->parent_id, 'image' => P::url($g->image)])->values(),
            'departure_places' => DeparturePlace::withoutGlobalScopes()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->trAll('name'), 'meeting_point' => $p->meeting_point])->values(),
            'airlines' => Airline::where('is_active', true)->orderBy('name')->get(['id', 'name', 'iata_code', 'is_low_cost']),
            'amenities' => Amenity::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($a) => ['key' => $a->key, 'name' => $a->trAll('name'), 'icon' => $a->icon, 'category' => $a->category])->values(),
            'enums' => [
                'tour_types' => collect(\App\Enums\TourType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->labels()]),
                'event_categories' => collect(\App\Enums\EventCategory::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->labels()]),
                'accommodation_types' => collect(\App\Enums\AccommodationType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->labels()]),
                'boards' => collect(\App\Enums\Board::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->labels()]),
                'visa_categories' => collect(\App\Enums\VisaCategory::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->labels()]),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Homepage slider: image, supertitle, title, subtitle and button (MK / EN) */
    public function slider(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->sliderItems($this->agencyId($request))]);
    }

    protected function sliderItems(int $agencyId): array
    {
        $both = fn (\App\Models\SliderImage $s, string $f) => ['mk' => $s->getTranslation($f, 'mk', false) ?: null, 'en' => $s->getTranslation($f, 'en', false) ?: null];

        return \App\Models\SliderImage::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'image' => P::url($s->path),
                'supertitle' => $both($s, 'supertitle'),
                'title' => $both($s, 'title'),
                'subtitle' => $both($s, 'subtitle'),
                'button' => ['label' => $both($s, 'button_label'), 'url' => $s->link, 'new_tab' => (bool) $s->open_in_new_tab],
                'button2' => ['label' => $both($s, 'button2_label'), 'url' => $s->link2],
                'updated_at' => $s->updated_at?->toIso8601String(),
            ])->values()->all();
    }

    /** Approved reviews (same list as /reviews; used by push mode) */
    public function reviews(Request $request): JsonResponse
    {
        return response()->json(['data' => \App\Http\Controllers\Api\ReviewController::items($this->agencyId($request))]);
    }

    /** Live chat widget settings for the website (no secrets) */
    public function chat(Request $request): JsonResponse
    {
        return response()->json(['data' => \App\Services\Ai\ChatConfig::forWebsite($this->agencyId($request))]);
    }

    /* ---------------- tours ---------------- */

    public function tours(Request $request): JsonResponse
    {
        $q = $this->published(Tour::class, $request)->with(['destination', 'group', 'departures']);
        $this->search($q, $request->query('q'), ['title', 'location', 'country']);
        $q->when($request->query('type'), fn ($q, $v) => $q->where('tour_type', $v))
            ->when($request->query('destination'), fn ($q, $v) => $q->where('destination_id', $v))
            ->when($request->query('group'), fn ($q, $v) => $q->where('group_id', $v))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->boolean('flight'), fn ($q) => $q->where('is_flight', true))
            ->when($request->boolean('last_minute'), fn ($q) => $q->where(fn ($w) => $w->lastMinute()->orWhereHas('departures', fn ($d) => $d->where('is_last_minute', true)->whereDate('start_date', '>=', today()))))
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereHas('departures', fn ($d) => $d->whereDate('start_date', '>=', $v)))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereHas('departures', fn ($d) => $d->whereDate('start_date', '<=', $v)))
            ->when($request->query('departure_place'), fn ($q, $v) => $q->whereHas('departurePlaceLinks', fn ($d) => $d->where('departure_place_id', $v)))
            ->when($request->query('price_max'), fn ($q, $v) => $q->where('price_from', '<=', $v));

        match ($request->query('sort')) {
            'price' => $q->orderBy('price_from'),
            'newest' => $q->latest(),
            default => $q->orderByDesc('is_featured')->orderBy('sort_order')->latest(),
        };

        return $this->paginate($q, $request, fn (Tour $t) => P::tourSummary($t));
    }

    public function tour(Request $request, string $slug): JsonResponse
    {
        $t = $this->published(Tour::class, $request)->where('slug', $slug)
            ->with(['destination', 'group', 'airline', 'days', 'departures', 'extras', 'departurePlaceLinks.departurePlace', 'media'])
            ->firstOrFail();

        return response()->json(['data' => P::tour($t)]);
    }

    /* ---------------- events ---------------- */

    public function events(Request $request): JsonResponse
    {
        $q = $this->published(Event::class, $request);
        $this->search($q, $request->query('q'), ['title', 'performer', 'venue', 'city']);
        $q->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('city'), fn ($q, $v) => $q->where('city', $v))
            ->when($request->boolean('last_minute'), fn ($q) => $q->lastMinute())
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when(! $request->boolean('past'), fn ($q) => $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '>=', now()->startOfDay())))
            ->orderBy('starts_at');

        return $this->paginate($q, $request, fn (Event $e) => P::eventSummary($e));
    }

    public function event(Request $request, string $slug): JsonResponse
    {
        $e = $this->published(Event::class, $request)->where('slug', $slug)->with(['tickets', 'media', 'tour'])->firstOrFail();

        return response()->json(['data' => P::event($e)]);
    }

    /* ---------------- accommodations ---------------- */

    public function accommodations(Request $request): JsonResponse
    {
        $q = $this->published(Accommodation::class, $request)->with(['destination', 'amenities']);
        $this->search($q, $request->query('q'), ['name', 'place', 'region', 'country']);
        $q->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('destination'), fn ($q, $v) => $q->where('destination_id', $v))
            ->when($request->query('group'), fn ($q, $v) => $q->where('group_id', $v))
            ->when($request->query('stars'), fn ($q, $v) => $q->where('stars', '>=', $v))
            ->when($request->query('board'), fn ($q, $v) => $q->whereHas('rooms', fn ($r) => $r->where('board', $v)))
            ->when($request->query('amenities'), fn ($q, $v) => collect(explode(',', $v))->each(fn ($k) => $q->whereHas('amenities', fn ($a) => $a->where('key', trim($k)))))
            ->when($request->boolean('last_minute'), fn ($q) => $q->lastMinute())
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->query('price_max'), fn ($q, $v) => $q->where('price_from', '<=', $v));

        match ($request->query('sort')) {
            'price' => $q->orderBy('price_from'),
            'stars' => $q->orderByDesc('stars'),
            default => $q->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name'),
        };

        return $this->paginate($q, $request, fn (Accommodation $a) => P::accommodationSummary($a));
    }

    public function accommodation(Request $request, string $slug): JsonResponse
    {
        $a = $this->published(Accommodation::class, $request)->where('slug', $slug)
            ->with(['destination', 'amenities', 'rooms.rates', 'rooms.media', 'media'])->firstOrFail();

        return response()->json(['data' => P::accommodation($a)]);
    }

    /** Price for a stay: ?from=2026-07-01&to=2026-07-08&adults=2&children=1 */
    public function quote(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date|after_or_equal:today',
            'to' => 'required|date|after:from',
            'adults' => 'integer|min:1|max:12',
            'children' => 'integer|min:0|max:8',
        ]);

        $a = $this->published(Accommodation::class, $request)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $a->quote($data['from'], $data['to'], (int) ($data['adults'] ?? 2), (int) ($data['children'] ?? 0))]);
    }

    /** Fixed terms of an accommodation with price and free units per room (website term table) */
    public function terms(Request $request, string $slug): JsonResponse
    {
        $a = $this->published(Accommodation::class, $request)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $a->book_by_terms ? $a->termTable() : ['terms' => [], 'rooms' => []]]);
    }

    /** Free units per room and night (for the calendar on the website); bookings are not exposed */
    public function availability(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        if ($from->diffInDays($to) > 92) {
            $to = $from->copy()->addDays(92);
        }

        $a = $this->published(Accommodation::class, $request)->where('slug', $slug)->firstOrFail();
        $result = app(\App\Services\Availability\AvailabilityService::class)->forAccommodation($a, $from, $to, false);
        $rooms = $a->rooms()->where('is_active', true)->get()->keyBy('id');

        return response()->json(['data' => [
            'from' => $result['from'],
            'to' => $result['to'],
            'source' => $result['source'],
            'rooms' => collect($result['rooms'])->filter(fn ($r) => $rooms->has($r['id']))->map(fn ($r) => [
                'room_id' => $r['id'],
                'name' => $rooms[$r['id']]->trAll('name'),
                'days' => collect($r['days'])->map(fn ($d) => [
                    'free' => $d['free'],
                    'price' => $d['price'],
                ])->all(),
            ])->values()->all(),
        ]]);
    }

    /* ---------------- charters ---------------- */

    /** Upcoming charter flights / seats (active only) */
    public function charters(Request $request): JsonResponse
    {
        $rows = \App\Models\Charter::query()->where('agency_id', $this->agencyId($request))->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('departure_date')->orWhereDate('departure_date', '>=', today()))
            ->orderBy('departure_date')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'region' => $c->region,
                'destination' => $c->destination,
                'departure_place' => $c->departure_place,
                'departure_date' => $c->departure_date?->toDateString(),
                'return_date' => $c->return_date?->toDateString(),
                'price' => (float) $c->price,
                'transport_only_price' => $c->transport_only_price !== null ? (float) $c->transport_only_price : null,
                'currency' => $c->currency,
                'seats_left' => $c->seatsLeft(),
                'note' => $c->note,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows->values()]);
    }

    /* ---------------- visas ---------------- */

    public function visas(Request $request): JsonResponse
    {
        $q = $this->published(VisaType::class, $request);
        $this->search($q, $request->query('q'), ['country', 'name']);
        $q->when($request->query('country'), fn ($q, $v) => $q->where(fn ($w) => $w->where('country_code', $v)->orWhere('country', $v)))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->orderBy('sort_order')->orderBy('country');

        return $this->paginate($q, $request, fn (VisaType $v) => P::visaSummary($v));
    }

    public function visa(Request $request, string $slug): JsonResponse
    {
        $v = $this->published(VisaType::class, $request)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => P::visa($v)]);
    }

    /* ---------------- last minute (all types) ---------------- */

    public function lastMinute(Request $request): JsonResponse
    {
        $tours = $this->published(Tour::class, $request)->with(['destination', 'group', 'departures'])
            ->where(fn ($w) => $w->lastMinute()->orWhereHas('departures', fn ($d) => $d->where('is_last_minute', true)->whereDate('start_date', '>=', today())))
            ->get()->map(fn ($t) => P::tourSummary($t));
        $acc = $this->published(Accommodation::class, $request)->with(['destination', 'amenities'])->lastMinute()->get()->map(fn ($a) => P::accommodationSummary($a));
        $events = $this->published(Event::class, $request)->lastMinute()->get()->map(fn ($e) => P::eventSummary($e));
        $visas = $this->published(VisaType::class, $request)->lastMinute()->get()->map(fn ($v) => P::visaSummary($v));

        return response()->json(['data' => $tours->concat($acc)->concat($events)->concat($visas)->values()]);
    }

    /**
     * Sync cursor for the website: everything changed (or deleted) since ?since=ISO-8601.
     * The website stores "server_time" and sends it as "since" next time.
     */
    public function changes(Request $request): JsonResponse
    {
        $since = $request->filled('since') ? Carbon::parse($request->query('since')) : Carbon::createFromTimestamp(0);
        $agency = $this->agencyId($request);

        $changed = collect();
        foreach (['tour' => Tour::class, 'event' => Event::class, 'accommodation' => Accommodation::class, 'visa' => VisaType::class] as $type => $class) {
            $class::query()->where('agency_id', $agency)->where('updated_at', '>', $since)->get(['id', 'slug', 'is_active', 'updated_at'])
                ->each(fn ($m) => $changed->push(['type' => $type, 'id' => $m->id, 'slug' => $m->slug, 'active' => (bool) $m->is_active, 'updated_at' => $m->updated_at->toIso8601String()]));
        }

        $deleted = DeletedRecord::where('agency_id', $agency)->where('deleted_at', '>', $since)->get()
            ->map(fn ($d) => ['type' => $d->type, 'id' => $d->record_id, 'slug' => $d->slug, 'deleted_at' => $d->deleted_at->toIso8601String()]);

        return response()->json(['changed' => $changed->values(), 'deleted' => $deleted->values(), 'server_time' => now()->toIso8601String()]);
    }
}
