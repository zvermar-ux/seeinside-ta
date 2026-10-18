<?php

namespace App\Filament\Resources\VisaTypeResource\Pages;

use App\Filament\Resources\VisaTypeResource;
use App\Filament\Concerns\CreatesGallery;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaType extends CreateRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use CreatesGallery;

    protected function afterCreate(): void
    {
        $this->storeGalleryUploads();
    }

    protected static string $resource = VisaTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->aiActions()];
    }
}
