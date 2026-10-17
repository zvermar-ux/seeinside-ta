<?php

namespace App\Filament\Resources\TravelObjectResource\Pages;

use App\Filament\Resources\TravelObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTravelObject extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = TravelObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\LocaleSwitcher::make()];
    }

    protected function getRedirectUrl(): string
    {
        // Continue on the edit page so images can be added right away
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
