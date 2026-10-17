<?php

namespace App\Filament\Resources\CharterResource\Pages;

use App\Filament\Resources\CharterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharters extends ListRecords
{
    protected static string $resource = CharterResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
