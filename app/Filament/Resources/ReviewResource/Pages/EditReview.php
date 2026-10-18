<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ReviewResource::applyItem($data, $this->data['item'] ?? null);
        if (($data['status'] ?? null) === \App\Models\Review::APPROVED && ! ($this->record?->approved_at ?? null)) {
            $data['approved_at'] = now();
        }


        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
