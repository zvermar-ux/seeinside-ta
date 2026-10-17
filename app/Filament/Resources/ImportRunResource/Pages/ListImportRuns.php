<?php

namespace App\Filament\Resources\ImportRunResource\Pages;

use App\Filament\Resources\ImportRunResource;
use Filament\Resources\Pages\ListRecords;

class ListImportRuns extends ListRecords
{
    protected static string $resource = ImportRunResource::class;

    public function getSubheading(): ?string
    {
        return __('Imports are started on the server with: php artisan aries:import');
    }
}
