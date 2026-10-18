<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroup extends EditRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use EditRecord\Concerns\Translatable;

    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->aiActions(), Actions\LocaleSwitcher::make(), Actions\DeleteAction::make()];
    }
}
