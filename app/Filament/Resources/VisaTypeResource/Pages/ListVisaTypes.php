<?php

namespace App\Filament\Resources\VisaTypeResource\Pages;

use App\Filament\Resources\VisaTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisaTypes extends ListRecords
{
    protected static string $resource = VisaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
