<?php

namespace App\Filament\Resources\SupplierConnectionResource\Pages;

use App\Filament\Resources\SupplierConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSupplierConnections extends ManageRecords
{
    protected static string $resource = SupplierConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }
}
