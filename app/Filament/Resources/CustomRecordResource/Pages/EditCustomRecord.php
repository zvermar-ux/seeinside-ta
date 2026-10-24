<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomRecord extends EditRecord
{
    protected static string $resource = CustomRecordResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->tr('title');
    }

    public function getBreadcrumbs(): array
    {
        $t = $this->record->recordType;

        return $t ? [CustomRecordResource::getUrl('index', ['type' => $t->slug]) => $t->label(), __('Edit')] : [];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()->successRedirectUrl(CustomRecordResource::getUrl('index', ['type' => $this->record->recordType?->slug]))];
    }
}
