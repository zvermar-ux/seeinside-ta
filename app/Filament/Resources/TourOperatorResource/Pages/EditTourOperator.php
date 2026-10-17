<?php

namespace App\Filament\Resources\TourOperatorResource\Pages;

use App\Filament\Resources\TourOperatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTourOperator extends EditRecord
{
    protected static string $resource = TourOperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
