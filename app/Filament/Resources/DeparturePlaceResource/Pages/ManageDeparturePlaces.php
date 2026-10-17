<?php

namespace App\Filament\Resources\DeparturePlaceResource\Pages;

use App\Filament\Resources\DeparturePlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeparturePlaces extends ManageRecords
{
    protected static string $resource = DeparturePlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
