<?php

namespace App\Filament\Widgets;

use App\Enums\ObjectType;
use App\Filament\Resources\TravelObjectResource;
use App\Models\Agency;
use App\Models\Subagent;
use App\Models\TravelObject;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class OfferStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('objects.view');
    }

    protected function getStats(): array
    {
        $byType = TravelObject::query()
            ->selectRaw('type, count(*) as total, sum(case when is_active = 1 then 1 else 0 end) as active')
            ->groupBy('type')->get()->keyBy(fn ($r) => $r->type instanceof ObjectType ? $r->type->value : $r->type);

        $withoutImages = TravelObject::query()->where('is_active', true)->doesntHave('images')->count();

        $stat = function (ObjectType $type) use ($byType) {
            $row = $byType->get($type->value);

            return Stat::make($type->getPluralLabel(), (int) ($row->total ?? 0))
                ->description(__(':count active on website', ['count' => (int) ($row->active ?? 0)]))
                ->descriptionIcon('heroicon-m-eye')
                ->icon($type->getIcon())
                ->color($type->getColor())
                ->url(TravelObjectResource::getUrl('index', ['activeTab' => $type->value]));
        };

        $stats = [
            $stat(ObjectType::Arrangement),
            $stat(ObjectType::Hotel),
            Stat::make(__('Active without images'), $withoutImages)
                ->description(__('Add photos to sell better'))
                ->descriptionIcon('heroicon-m-photo')
                ->color($withoutImages ? 'danger' : 'success')
                ->url(TravelObjectResource::getUrl('index', ['tableFilters' => ['is_active' => ['value' => 1], 'has_images' => ['value' => 0]]])),
        ];

        if (Auth::user()->isSuperAdmin()) {
            $stats[] = Stat::make(__('Agencies'), Agency::count())
                ->description(__(':count active', ['count' => Agency::where('is_active', true)->count()]))
                ->icon('heroicon-o-building-storefront');
        } else {
            $stats[] = Stat::make(__('Subagents'), Subagent::count())
                ->description(__(':count with access', ['count' => Subagent::where('has_access', true)->count()]))
                ->icon('heroicon-o-user-group');
        }

        return $stats;
    }
}
