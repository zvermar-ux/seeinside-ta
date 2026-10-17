<?php

namespace App\Filament\Resources\TourOperatorResource\Pages;

use App\Filament\Resources\TourOperatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTourOperators extends ListRecords
{
    protected static string $resource = TourOperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
