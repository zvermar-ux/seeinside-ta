<?php

namespace App\Filament\Resources\VisaApplicationResource\Pages;

use App\Filament\Resources\VisaApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVisaApplication extends ViewRecord
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
