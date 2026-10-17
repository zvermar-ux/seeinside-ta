<?php

namespace App\Filament\Resources\CharterResource\Pages;

use App\Filament\Resources\CharterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCharter extends EditRecord
{
    protected static string $resource = CharterResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
