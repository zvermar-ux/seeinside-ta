<?php

namespace App\Filament\Resources\SubagentResource\Pages;

use App\Filament\Resources\SubagentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubagent extends EditRecord
{
    protected static string $resource = SubagentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
