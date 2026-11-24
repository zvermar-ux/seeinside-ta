<?php

namespace App\Filament\Resources\SupplierDestinationResource\Pages;

use App\Filament\Resources\SupplierDestinationResource;
use App\Services\Hotels\HotelNamesMk;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSupplierDestinations extends ListRecords
{
    protected static string $resource = SupplierDestinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mk')->label(__('Macedonian names (AI)'))->icon('heroicon-o-language')
                ->modalHeading(__('Macedonian names of destinations and areas'))
                ->modalDescription(__('AI writes the names of the destinations in the website search and of their areas in Macedonian Cyrillic (Lefkada → Лефкада, Central and North Greece → Централна и Северна Грција). Names you already wrote stay. You can change every name with "Macedonian names" on the row.'))
                ->modalSubmitActionLabel(__('Translate'))
                ->action(function () {
                    @set_time_limit(300);
                    try {
                        $n = HotelNamesMk::translate();
                        Notification::make()->success()->title(__('Done'))->body(__(':count names written in Macedonian.', ['count' => $n]))->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
                    }
                }),
        ];
    }
}
