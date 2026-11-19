<?php

namespace App\Filament\Resources\AgencyResource\Pages;

use App\Filament\Resources\AgencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /** the payment keys are hidden from arrays (API, logs) – the form still needs them */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['payment_settings' => (array) ($this->record->payment_settings ?? [])];
    }
}
