<?php

namespace App\Filament\Resources\SubagentResource\Pages;

use App\Filament\Resources\SubagentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubagents extends ListRecords
{
    protected static string $resource = SubagentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
