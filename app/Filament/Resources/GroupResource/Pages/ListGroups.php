<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Enums\GroupType;
use App\Filament\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->icon('heroicon-o-plus')
                ->url(fn () => GroupResource::getUrl('create', ['type' => $this->activeTab === 'destination' ? 'destination' : 'group'])),
        ];
    }

    public function getTabs(): array
    {
        return [
            'group' => Tab::make(__('Groups'))->icon('heroicon-o-rectangle-stack')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', GroupType::Group->value)),
            'destination' => Tab::make(__('Destinations'))->icon('heroicon-o-map-pin')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', GroupType::Destination->value)),
        ];
    }
}
