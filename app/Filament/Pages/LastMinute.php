<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\TourResource;
use App\Filament\Resources\VisaTypeResource;
use App\Models\Accommodation;
use App\Models\Event;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\VisaType;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class LastMinute extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.last-minute';

    public static function getNavigationLabel(): string
    {
        return 'Last minute';
    }

    public function getTitle(): string
    {
        return 'Last minute';
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return $u && ($u->can('tours.view') || $u->can('accommodations.view') || $u->can('events.view'));
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Tour::lastMinute()->count() + Event::lastMinute()->count() + Accommodation::lastMinute()->count()
            + TourDeparture::where('is_last_minute', true)->whereDate('start_date', '>=', today())->whereHas('tour')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        $fmt = fn ($p, $c) => $p !== null ? number_format((float) $p, 0, ',', '.').' '.$c : null;

        $sections = [];

        $tours = Tour::lastMinute()->get()->map(fn (Tour $t) => [
            'title' => $t->tr('title'), 'meta' => $t->location, 'price' => $fmt($t->price_from, $t->currency),
            'until' => $t->last_minute_until?->format('d.m.Y'), 'url' => TourResource::getUrl('edit', ['record' => $t]), 'image' => $t->cover_image,
        ]);
        $deps = TourDeparture::with('tour')->where('is_last_minute', true)->whereDate('start_date', '>=', today())->whereHas('tour')->orderBy('start_date')->get()
            ->map(fn (TourDeparture $d) => [
                'title' => $d->tour->tr('title'), 'meta' => __('Departure').' '.$d->start_date->format('d.m.Y'),
                'price' => $fmt($d->adultPrice(), $d->tour->currency), 'until' => null,
                'url' => TourResource::getUrl('edit', ['record' => $d->tour]), 'image' => $d->tour->cover_image,
            ]);
        $sections[__('Organized tours')] = $tours->concat($deps)->all();

        $sections[__('Hotels & apartments')] = Accommodation::lastMinute()->get()->map(fn (Accommodation $a) => [
            'title' => $a->name, 'meta' => trim($a->place.', '.$a->country, ', '), 'price' => $fmt($a->price_from, $a->currency),
            'until' => $a->last_minute_until?->format('d.m.Y'), 'url' => AccommodationResource::getUrl('edit', ['record' => $a]), 'image' => $a->cover_image,
        ])->all();

        $sections[__('Events')] = Event::lastMinute()->get()->map(fn (Event $e) => [
            'title' => $e->tr('title'), 'meta' => $e->starts_at?->format('d.m.Y H:i'), 'price' => $fmt($e->price_from, $e->currency),
            'until' => $e->last_minute_until?->format('d.m.Y'), 'url' => EventResource::getUrl('edit', ['record' => $e]), 'image' => $e->cover_image,
        ])->all();

        $sections[__('Visa services')] = VisaType::lastMinute()->get()->map(fn (VisaType $v) => [
            'title' => $v->country.' — '.$v->tr('name'), 'meta' => null, 'price' => $fmt($v->totalPrice(), $v->currency),
            'until' => $v->last_minute_until?->format('d.m.Y'), 'url' => VisaTypeResource::getUrl('edit', ['record' => $v]), 'image' => $v->cover_image,
        ])->all();

        return ['sections' => $sections];
    }
}
