<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Pages\Reports;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Reports\OfferReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Money at a glance on the dashboard: this month's sales and payments, what clients owe */
class FinanceStats extends BaseWidget
{
    protected static ?int $sort = -3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Reports::canSee();
    }

    protected function getStats(): array
    {
        $m = fn ($v) => OfferReport::money($v);
        $start = now()->startOfMonth();
        $sold = Booking::query()->whereIn('status', OfferReport::DEFAULT_STATUSES)->where('created_at', '>=', $start);
        $soldPrev = Booking::query()->whereIn('status', OfferReport::DEFAULT_STATUSES)->whereBetween('created_at', [$start->copy()->subMonth(), now()->subMonth()]);
        $in = (float) BookingPayment::query()->where('paid_at', '>=', $start->toDateString())->where('amount', '>', 0)->sum('amount');
        $open = Booking::query()->whereIn('status', OfferReport::DEFAULT_STATUSES)->whereColumn('paid_amount', '<', 'total');
        $owed = (float) (clone $open)->selectRaw('sum(total - paid_amount) as d')->value('d');
        $soon = (clone $open)->where(fn ($q) => $q->whereBetween('date_from', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->orWhereHas('departure', fn ($d) => $d->whereBetween('start_date', [now()->toDateString(), now()->addDays(14)->toDateString()])))->count();
        $s = (float) (clone $sold)->sum('total');
        $p = (float) $soldPrev->sum('total');

        return [
            Stat::make(__('Sold this month'), $m($s))
                ->description((clone $sold)->count().' '.__('bookings').($p > 0 ? ' · '.($s >= $p ? '▲' : '▼').' '.abs(round(($s - $p) / $p * 100)).'% '.__('vs last month') : ''))
                ->color($s >= $p ? 'success' : 'warning')->icon('heroicon-o-shopping-bag')
                ->url(Reports::getUrl(['mode' => 'period', 'period' => 'month'])),
            Stat::make(__('Payments received this month'), $m($in))->icon('heroicon-o-banknotes')->color('primary')
                ->description(__('by payment date')),
            Stat::make(__('Clients owe the agency'), $m($owed))->icon('heroicon-o-exclamation-triangle')->color($owed > 0 ? 'warning' : 'success')
                ->description($soon ? __(':n of them travel in the next 14 days', ['n' => $soon]) : __('nobody travels unpaid in the next 14 days')),
        ];
    }
}
