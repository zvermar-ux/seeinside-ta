<?php

namespace App\Filament\Pages;

use App\Enums\SupplierProvider;
use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\TourResource;
use App\Models\Accommodation;
use App\Models\AccommodationRoom;
use App\Models\StayTerm;
use App\Models\SupplierConnection;
use App\Models\SupplierProduct;
use App\Models\TermPrice;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Mcs\McsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Links with the old Aries system at a glance: every apartment and its rooms with the Aries Id and Room-Id,
 * whether they are linked, and how many terms / room prices / departures came from Aries. Linking right here.
 */
class AriesLinks extends Page
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.aries-links';

    public string $search = '';

    public string $show = 'all';   // all | missing | linked

    public static function defaultNavigationLabel(): string
    {
        return __('Links with Aries');
    }

    public function getTitle(): string
    {
        return __('Links with the old Aries system');
    }

    public static function connection(): ?SupplierConnection
    {
        return SupplierConnection::query()->where('provider', SupplierProvider::Mcs->value)->where('is_active', true)->first();
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return $u && $u->can('accommodations.manage') && static::connection() !== null;
    }

    /** Link an apartment to an object of the old system ("p:151" / "h:12", empty = unlink) */
    public function linkAccommodation(int $id, ?string $ref): void
    {
        $c = static::connection();
        $a = Accommodation::query()->findOrFail($id);
        if (! $ref) {
            $a->forceFill(['supplier_connection_id' => null, 'provider_code' => null])->save();
            $a->rooms()->update(['provider_code' => null]);
            Notification::make()->title(__('Unlinked: :n', ['n' => $a->name]))->success()->send();

            return;
        }
        $a->forceFill(['supplier_connection_id' => $c->id, 'provider_code' => $ref, 'use_supplier_availability' => false])->save();
        $p = McsService::accommodationProduct($a->fresh());
        $n = $p ? McsService::linkRooms($a->fresh('rooms'), $p) : 0;
        Notification::make()->title(__('Linked: :n', ['n' => $a->name]))->body($n ? __('Rooms linked automatically: :n', ['n' => $n]) : null)->success()->send();
    }

    public function linkRoom(int $roomId, ?string $code): void
    {
        $r = AccommodationRoom::query()->whereHas('accommodation')->findOrFail($roomId);
        $r->forceFill(['provider_code' => $code ?: null])->save();
        Notification::make()->title($code ? __('Room linked') : __('Room unlinked'))->success()->send();
    }

    public function linkTour(int $id, ?string $code): void
    {
        $c = static::connection();
        $t = Tour::query()->findOrFail($id);
        $rooms = $code ? (array) (SupplierProduct::query()->where('supplier_connection_id', $c->id)->where('kind', 'package')->where('code', $code)->value('rooms') ?? []) : [];
        $t->forceFill(['supplier_connection_id' => $code ? $c->id : null, 'provider_code' => $code ?: null, 'provider_room_code' => count($rooms) === 1 ? $rooms[0]['code'] : null])->save();
        Notification::make()->title($code ? __('Linked: :n', ['n' => $t->tr('title')]) : __('Unlinked: :n', ['n' => $t->tr('title')]))->success()->send();
    }

    public function linkTourRoom(int $id, ?string $code): void
    {
        Tour::query()->findOrFail($id)->forceFill(['provider_room_code' => $code ?: null])->save();
    }

    protected function getViewData(): array
    {
        $c = static::connection();
        $products = SupplierProduct::query()->where('supplier_connection_id', $c?->id)->where('is_active', true)->orderBy('name')->get();
        $options = $products->mapWithKeys(fn ($p) => [McsService::refKey($p) => ($p->kind === 'package' ? __('Package') : __('Hotel')).' · Id '.$p->code.' · '.$p->name.($p->city ? ' ('.$p->city.')' : '')])->all();
        $byRef = $products->keyBy(fn ($p) => McsService::refKey($p));
        $q = trim($this->search);

        $mcsId = $c?->id;
        // own accommodation: apartments / villas always; hotels only when the agency leases or owns them or they are in the old Aries app –
        // never hotels that come from a booking system (Hotelbeds…)
        $accs = Accommodation::query()
            ->where(fn ($q) => $q->whereNull('supplier_connection_id')->orWhere('supplier_connection_id', $mcsId))
            ->where(fn ($q) => $q->where('type', '!=', 'hotel')->orWhereIn('ownership', ['lease', 'own'])
                ->orWhere(fn ($q) => $q->where('supplier_connection_id', $mcsId)->whereNotNull('provider_code')->where('provider_code', '!=', '')))
            ->with('rooms')->orderBy('name')->get()
            ->filter(fn ($a) => $q === '' || mb_stripos($a->name, $q) !== false);
        $termIds = StayTerm::query()->whereIn('accommodation_id', $accs->pluck('id'))->where('date_from', '>=', today())->get(['id', 'accommodation_id', 'supplier_synced_at']);
        $prices = TermPrice::query()->whereIn('stay_term_id', $termIds->pluck('id'))->get(['stay_term_id', 'accommodation_room_id', 'supplier_synced_at', 'stop_sale']);
        $termsBy = $termIds->groupBy('accommodation_id');

        $rows = $accs->map(function (Accommodation $a) use ($c, $byRef, $termsBy, $prices) {
            $linked = $c && $a->supplier_connection_id === $c->id && filled($a->provider_code);
            $ref = $linked ? (str_contains((string) $a->provider_code, ':') ? $a->provider_code : 'h:'.$a->provider_code) : null;
            $p = $ref ? $byRef[$ref] ?? null : null;
            $terms = $termsBy[$a->id] ?? collect();
            $tp = $prices->whereIn('stay_term_id', $terms->pluck('id'));
            $rooms = $a->rooms->map(fn ($r) => ['id' => $r->id, 'name' => $r->tr('name'), 'cap' => $r->max_adults.'+'.$r->max_children, 'code' => (string) $r->provider_code,
                'prices' => $tp->where('accommodation_room_id', $r->id)->whereNotNull('supplier_synced_at')->count(), 'stop' => $tp->where('accommodation_room_id', $r->id)->where('stop_sale', true)->count()]);
            $roomsOk = $rooms->filter(fn ($r) => $r['code'] !== '')->count();

            return [
                'id' => $a->id, 'name' => $a->name, 'place' => trim(implode(', ', array_filter([$a->city ?? null, $a->country ?? null])), ', '),
                'edit' => AccommodationResource::getUrl('edit', ['record' => $a]), 'ref' => $ref, 'product' => $p, 'roomOptions' => $p ? $p->roomOptions() : [],
                'rooms' => $rooms->all(), 'roomsOk' => $roomsOk,
                'status' => ! $linked ? 'none' : ($roomsOk === $rooms->count() && $rooms->count() ? 'ok' : 'part'),
                'terms' => $terms->count(), 'termsAries' => $terms->whereNotNull('supplier_synced_at')->count(),
                'lastSync' => $terms->max('supplier_synced_at'), 'pricesAries' => $tp->whereNotNull('supplier_synced_at')->count(), 'stop' => $tp->where('stop_sale', true)->count(),
                'sync' => (bool) $a->sync_supplier_prices,
            ];
        })->filter(fn ($r) => $this->show === 'all' || ($this->show === 'missing' ? $r['status'] !== 'ok' : $r['status'] === 'ok'))->values();

        $packages = $products->where('kind', 'package');
        $tours = Tour::query()->orderBy('id', 'desc')->get()
            ->filter(fn ($t) => $q === '' || mb_stripos((string) $t->tr('title'), $q) !== false)
            ->map(function (Tour $t) use ($c, $packages) {
                $linked = $c && $t->supplier_connection_id === $c->id && filled($t->provider_code);
                $p = $linked ? $packages->firstWhere('code', $t->provider_code) : null;
                $deps = TourDeparture::query()->where('tour_id', $t->id)->where('start_date', '>=', today());

                return ['id' => $t->id, 'name' => $t->tr('title'), 'edit' => TourResource::getUrl('edit', ['record' => $t]), 'code' => (string) $t->provider_code, 'room' => (string) $t->provider_room_code,
                    'roomOptions' => $p ? $p->roomOptions() : [], 'status' => $linked ? 'ok' : 'none',
                    'deps' => (clone $deps)->count(), 'depsAries' => (clone $deps)->whereNotNull('supplier_synced_at')->count(), 'lastSync' => (clone $deps)->max('supplier_synced_at')];
            })
            ->filter(fn ($r) => $this->show === 'all' || ($this->show === 'missing' ? $r['status'] !== 'ok' : $r['status'] === 'ok'))->values();

        return [
            'connection' => $c, 'options' => $options, 'packageOptions' => $packages->mapWithKeys(fn ($p) => [$p->code => 'Id '.$p->code.' · '.$p->name])->all(),
            'rows' => $rows, 'tours' => $tours,
            'stats' => [
                'acc' => $accs->count(), 'accOk' => $rows->where('status', 'ok')->count(),
                'rooms' => $rows->sum(fn ($r) => count($r['rooms'])), 'roomsOk' => $rows->sum('roomsOk'),
                'terms' => $rows->sum('termsAries'), 'prices' => $rows->sum('pricesAries'),
                'tours' => $tours->count(), 'toursOk' => $tours->where('status', 'ok')->count(),
                'last' => $c?->last_sync_at, 'result' => $c?->settings['last_sync_result'] ?? null,
            ],
        ];
    }
}
