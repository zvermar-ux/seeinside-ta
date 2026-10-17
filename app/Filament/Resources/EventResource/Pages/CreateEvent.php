<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Concerns\CreatesGallery;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    use CreatesGallery;

    protected static string $resource = EventResource::class;

    protected function afterCreate(): void
    {
        $this->storeGalleryUploads();
        $this->record->refresh()->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
