<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All')),
            'upcoming' => Tab::make(__('Upcoming'))->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('departures', fn ($q) => $q->whereDate('start_date', '>=', today()))),
            'last_minute' => Tab::make('Last minute')->icon('heroicon-o-bolt')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn ($q) => $q->where('is_last_minute', true)
                    ->orWhereHas('departures', fn ($d) => $d->where('is_last_minute', true)->whereDate('start_date', '>=', today())))),
            'inactive' => Tab::make(__('Inactive'))->icon('heroicon-o-eye-slash')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}
