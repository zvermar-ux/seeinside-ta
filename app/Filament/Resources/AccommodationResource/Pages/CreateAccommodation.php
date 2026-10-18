<?php

namespace App\Filament\Resources\AccommodationResource\Pages;

use App\Filament\Resources\AccommodationResource;
use App\Filament\Concerns\CreatesGallery;
use Filament\Resources\Pages\CreateRecord;

class CreateAccommodation extends CreateRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use CreatesGallery;

    protected static string $resource = AccommodationResource::class;

    protected function afterCreate(): void
    {
        $this->storeGalleryUploads();
        $this->record->refresh()->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->aiActions()];
    }
}
