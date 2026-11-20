<?php

namespace App\Filament\Resources\PhotoStoryResource\Pages;

use App\Filament\Resources\PhotoStoryResource;
use App\Models\PhotoStory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePhotoStory extends CreateRecord
{
    protected static string $resource = PhotoStoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['photos'] = PhotoStoryResource::photosFrom(array_values((array) ($data['photo_paths'] ?? [])), null);
        unset($data['photo_paths']);
        if (($data['status'] ?? null) === PhotoStory::APPROVED) {
            $data['approved_at'] = now();
            $data['approved_by'] = Auth::id();
        }

        return $data;
    }
}
