<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\BookingResource;
use App\Models\Accommodation;
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
        $u = Auth::user();

        return $u && ($u->can('bookings.view') || $u->can('accommodations.view'));
    }

    public function mount(): void
    {
        $this->start = $this->normalizedStart()->toDateString();
        if (! $this->accommodationId || ! Accommodation::whereKey($this->accommodationId)->exists()) {
            $this->accommodationId = Accommodation::query()->whereHas('rooms')->orderBy('name')->value('id');
        }
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

        return [
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
