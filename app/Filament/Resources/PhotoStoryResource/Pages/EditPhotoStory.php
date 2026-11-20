<?php

namespace App\Filament\Resources\PhotoStoryResource\Pages;

use App\Filament\Resources\PhotoStoryResource;
use App\Models\PhotoStory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPhotoStory extends EditRecord
{
    protected static string $resource = PhotoStoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['photos'] = PhotoStoryResource::photosFrom(array_values((array) ($data['photo_paths'] ?? [])), $this->record);
        unset($data['photo_paths']);
        if (($data['status'] ?? null) === PhotoStory::APPROVED && ! $this->record->approved_at) {
            $data['approved_at'] = now();
            $data['approved_by'] = Auth::id();
        }

        return $data;
    }
}
