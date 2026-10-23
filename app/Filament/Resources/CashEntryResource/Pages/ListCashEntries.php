<?php

namespace App\Filament\Resources\CashEntryResource\Pages;

use App\Filament\Resources\CashEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashEntries extends ListRecords
{
    protected static string $resource = CashEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('journal')->label(__('Cash journal'))->icon('heroicon-o-book-open')->color('gray')->url(\App\Filament\Pages\CashJournal::getUrl()),
            Actions\CreateAction::make('out')->label(__('Cash payout'))->color('danger')->icon('heroicon-o-arrow-up-tray')->url(CashEntryResource::getUrl('create', ['direction' => 'out'])),
            Actions\CreateAction::make()->label(__('Cash receipt'))->color('success')->icon('heroicon-o-arrow-down-tray')->url(CashEntryResource::getUrl('create', ['direction' => 'in'])),
        ];
    }
}
