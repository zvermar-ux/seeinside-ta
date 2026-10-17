<?php

namespace App\Filament\Resources\VisaApplicationResource\Pages;

use App\Filament\Resources\VisaApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
