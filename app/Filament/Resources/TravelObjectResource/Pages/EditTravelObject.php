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
        return [
            Actions\Action::make('convert')->label(__('Create website offer'))->icon('heroicon-o-arrow-up-on-square')->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('A new, inactive record is created with the name, description, location and photos. Prices and dates are added there.'))
                ->action(fn () => $this->redirect(\App\Services\Aries\AriesConverter::convert($this->record))),
            \App\Filament\Resources\TravelObjectResource::aiConvertAction(Actions\Action::class),
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
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
