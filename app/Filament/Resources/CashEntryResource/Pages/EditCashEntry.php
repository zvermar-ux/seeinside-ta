<?php

namespace App\Filament\Resources\CashEntryResource\Pages;

use App\Filament\Resources\CashEntryResource;
use App\Services\Documents\Documents;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashEntry extends EditRecord
{
    protected static string $resource = CashEntryResource::class;

    public function getTitle(): string
    {
        return $this->record->title();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')->label(__('Print'))->icon('heroicon-o-printer')->color('gray')
                ->url(fn () => Documents::url('cash', $this->record->id), shouldOpenInNewTab: true),
            Actions\DeleteAction::make()->visible(fn () => ! $this->record->booking_payment_id),
        ];
    }
}
