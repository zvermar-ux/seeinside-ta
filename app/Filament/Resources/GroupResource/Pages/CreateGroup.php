<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGroup extends CreateRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->aiActions(), Actions\LocaleSwitcher::make()];
    }
}
