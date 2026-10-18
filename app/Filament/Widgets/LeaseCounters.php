<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports;
use App\Models\Accommodation;
use App\Services\Reports\OfferReport;
use Filament\Widgets\Widget;

/** Leased / owned villas and apartments: how much of the season costs is already covered */
class LeaseCounters extends Widget
{
    protected static ?int $sort = -1;

    protected static string $view = 'filament.widgets.lease-counters';

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return Reports::canSee() && Accommodation::query()->whereIn('ownership', ['lease', 'own'])->exists();
    }

    protected function getViewData(): array
    {
        return ['items' => Accommodation::query()->whereIn('ownership', ['lease', 'own'])->orderBy('name')->limit(12)->get()
            ->map(fn (Accommodation $a) => ['a' => $a, 'be' => (new OfferReport($a, OfferReport::leaseWindow($a)))->breakEven(),
                'url' => Reports::getUrl(['mode' => 'single', 'offer' => 'accommodation:'.$a->id])])];
    }
}
