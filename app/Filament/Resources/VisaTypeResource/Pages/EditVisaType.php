<?php

namespace App\Filament\Resources\VisaTypeResource\Pages;

use App\Filament\Resources\VisaTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisaType extends EditRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    protected static string $resource = VisaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->aiActions(), Actions\DeleteAction::make()];
    }

    public function getContentTabLabel(): ?string
    {
        return __('Details');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
