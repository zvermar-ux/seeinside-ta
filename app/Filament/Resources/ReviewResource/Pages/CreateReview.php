<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateReview extends CreateRecord
{
    protected static string $resource = ReviewResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ReviewResource::applyItem($data, $this->data['item'] ?? null);
        if (($data['status'] ?? null) === \App\Models\Review::APPROVED && ! ($this->record?->approved_at ?? null)) {
            $data['approved_at'] = now();
        }
        $data['source'] = 'manual';

        return $data;
    }

}
