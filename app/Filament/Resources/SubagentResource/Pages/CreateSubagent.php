<?php

namespace App\Filament\Resources\SubagentResource\Pages;

use App\Filament\Resources\SubagentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSubagent extends CreateRecord
{
    protected static string $resource = SubagentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
