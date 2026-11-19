<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\BookingResource;
use App\Models\Accommodation;
use App\Models\StayTerm;
use App\Services\Availability\AvailabilityService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/** Occupancy calendar: rooms / apartments × nights, free or booked, with quick booking of a free period */
class AvailabilityCalendar extends Page
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'availability';

    protected static string $view = 'filament.pages.availability-calendar';

    #[Url(as: 'accommodation')]
    public ?int $accommodationId = null;

    #[Url(as: 'from')]
    public ?string $start = null;

    public int $days = 31;

    public static function defaultNavigationLabel(): string
    {
        return __('Availability calendar');
    }

    public function getTitle(): string
    {
        return __('Availability calendar');
    }

    public static function canAccess(): bool
    {
        if (! \App\Models\Agency::moduleOn('accommodations')) {
            return false;
        }
        $u = Auth::user();

        return $u && ($u->can('bookings.view') || $u->can('accommodations.view'));
    }

    public function mount(): void
    {
        $given = filled($this->start);
        if (! $this->accommodationId || ! Accommodation::whereKey($this->accommodationId)->exists()) {
            $this->accommodationId = Accommodation::query()->whereHas('rooms')->orderBy('name')->value('id');
        }
        // fixed terms: the calendar opens on the first term that still has a free room
        $this->start = ($given ? null : $this->firstFreeTerm()) ?? $this->normalizedStart()->toDateString();
    }

    /** Start of the first future term with a room that is not stop-sale (apartments sold in fixed terms) */
    protected function firstFreeTerm(): ?string
    {
        $a = $this->accommodationId ? Accommodation::query()->find($this->accommodationId) : null;
        if (! $a || ! $a->book_by_terms) {
            return null;
        }
        $term = StayTerm::query()->where('accommodation_id', $a->id)->where('is_active', true)->whereDate('date_from', '>=', today())
            ->whereHas('prices', fn ($q) => $q->where('stop_sale', false)->whereNotNull('price'))
            ->orderBy('date_from')->first();

        return $term?->date_from?->toDateString();
    }

    protected function normalizedStart(): Carbon
    {
        try {
            return $this->start ? Carbon::parse($this->start)->startOfDay() : today()->startOfMonth();
        } catch (\Throwable) {
            return today()->startOfMonth();
        }
    }

    public function shift(int $months): void
    {
        $this->start = $this->normalizedStart()->addMonthsNoOverflow($months)->startOfMonth()->toDateString();
    }

    public function goToday(): void
    {
        $this->start = today()->subDays(3)->toDateString();
    }

    public function updatedAccommodationId(): void
    {
        $this->accommodationId = $this->accommodationId ?: null;
        if ($first = $this->firstFreeTerm()) {
            $this->start = $first;
        }
    }

    public function updatedStart(): void
    {
        $this->start = $this->normalizedStart()->toDateString();
    }

    protected function getViewData(): array
    {
        $accommodations = Accommodation::query()->orderBy('name')->get(['id', 'name', 'place', 'type', 'agency_id', 'supplier_connection_id']);
        $accommodation = $this->accommodationId ? Accommodation::with('supplierConnection')->find($this->accommodationId) : null;

        $from = $this->normalizedStart();
        $to = $from->copy()->addDays($this->days - 1);
        $data = $accommodation ? app(AvailabilityService::class)->forAccommodation($accommodation, $from, $to) : null;

        $canBook = BookingResource::canCreate();
        $bookingUrl = $canBook ? BookingResource::getUrl('create') : null;

        // fixed terms: a click on any night picks the whole term
        $terms = $accommodation && $accommodation->book_by_terms
            ? StayTerm::query()->where('accommodation_id', $accommodation->id)->where('is_active', true)
                ->whereDate('date_to', '>', $from)->whereDate('date_from', '<=', $to)->orderBy('date_from')->with('prices')->get()
                ->map(fn ($t) => ['id' => $t->id, 'from' => $t->date_from->toDateString(), 'to' => $t->date_to->toDateString(),
                    'stop' => $t->prices->where('stop_sale', true)->pluck('accommodation_room_id')->values()->all()])->values()->all()
            : [];

        return [
            'terms' => $terms,
            'accommodations' => $accommodations,
            'accommodation' => $accommodation,
            'data' => $data,
            'from' => $from,
            'to' => $to,
            'bookingUrl' => $bookingUrl,
            'editBookingUrl' => BookingResource::canViewAny() ? BookingResource::getUrl('edit', ['record' => '__ID__']) : null,
            'editAccommodationUrl' => $accommodation && AccommodationResource::canEdit($accommodation) ? AccommodationResource::getUrl('edit', ['record' => $accommodation]) : null,
            'today' => today()->toDateString(),
        ];
    }
}
