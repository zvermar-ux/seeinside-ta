<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    protected static string $resource = TourResource::class;

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

    protected function afterSave(): void
    {
        // recompute "price from" after the departures repeater has been saved
        $this->record->refresh()->save();
    }
}
