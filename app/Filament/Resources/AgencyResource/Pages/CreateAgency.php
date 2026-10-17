<?php

namespace App\Filament\Resources\AgencyResource\Pages;

use App\Filament\Resources\AgencyResource;
use App\Filament\Resources\UserResource;
use App\Models\DeparturePlace;
use App\Models\TourOperator;
use Filament\Resources\Pages\CreateRecord;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    protected function afterCreate(): void
    {
        // Every agency starts with an "own offer" source
        TourOperator::create([
            'agency_id' => $this->record->id,
            'name' => __('Own offer'),
            'is_own' => true,
        ]);

        DeparturePlace::seedDefaults($this->record->id);
    }

    protected function getRedirectUrl(): string
    {
        // Next step: create the agency admin
        return UserResource::getUrl('create', ['agency' => $this->record->id]);
    }
}
