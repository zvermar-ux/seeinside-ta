<?php

namespace App\Filament\Resources\TravelObjectResource\Pages;

use App\Enums\ObjectType;
use App\Filament\Resources\TravelObjectResource;
use App\Models\TravelObject;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTravelObjects extends ListRecords
{
    protected static string $resource = TravelObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        $counts = TravelObject::query()->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        $tabs = ['all' => Tab::make(__('All'))->badge($counts->sum())];

        foreach (ObjectType::cases() as $type) {
            if (! $counts->has($type->value) && $type === ObjectType::Apartment) {
                continue;
            }
            $tabs[$type->value] = Tab::make($type->getPluralLabel())
                ->icon($type->getIcon())
                ->badge($counts[$type->value] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->value));
        }

        return $tabs;
    }
}
