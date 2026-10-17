<?php

namespace App\Filament\Widgets;

use App\Models\TravelObject;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ObjectsByCountryChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = ['md' => 2, 'xl' => 2];

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Offer by country');
    }

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('objects.view');
    }

    protected function getData(): array
    {
        $rows = TravelObject::query()
            ->selectRaw("coalesce(nullif(country, ''), '?') as c, sum(case when is_active = 1 then 1 else 0 end) as active, sum(case when is_active = 1 then 0 else 1 end) as inactive")
            ->groupBy('c')->orderByRaw('count(*) desc')->limit(12)->get();

        return [
            'datasets' => [
                ['label' => __('Active'), 'data' => $rows->pluck('active')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#0ea5e9', 'borderRadius' => 4],
                ['label' => __('Inactive'), 'data' => $rows->pluck('inactive')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#cbd5e1', 'borderRadius' => 4],
            ],
            'labels' => $rows->pluck('c')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
