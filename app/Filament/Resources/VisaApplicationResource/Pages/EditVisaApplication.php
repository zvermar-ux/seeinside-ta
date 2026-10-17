<?php

namespace App\Filament\Resources\VisaApplicationResource\Pages;

use App\Filament\Resources\VisaApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisaApplication extends EditRecord
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
