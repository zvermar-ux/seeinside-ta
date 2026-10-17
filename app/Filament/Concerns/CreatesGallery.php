<?php

namespace App\Filament\Concerns;

/** Create pages: photos uploaded in the "Gallery" field become gallery items after the record exists */
trait CreatesGallery
{
    protected array $galleryUploads = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->galleryUploads = array_values((array) ($data['gallery_upload'] ?? []));
        unset($data['gallery_upload']);

        return $data;
    }

    protected function storeGalleryUploads(): void
    {
        foreach ($this->galleryUploads as $i => $path) {
            $this->record->media()->create([
                'path' => $path,
                'sort_order' => $i + 1,
                'is_main' => $i === 0,
            ]);
        }
    }
}
