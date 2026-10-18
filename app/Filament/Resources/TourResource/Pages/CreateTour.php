<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use App\Filament\Concerns\CreatesGallery;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use CreatesGallery;

    protected static string $resource = TourResource::class;

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
