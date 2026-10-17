<?php

namespace App\Filament\Resources\TravelObjectResource\Pages;

use App\Filament\Resources\TravelObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTravelObject extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = TravelObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\LocaleSwitcher::make(), Actions\DeleteAction::make()];
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
