<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\Documents\Documents;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return $this->record->title();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')->label(__('Print'))->icon('heroicon-o-printer')->color('gray')
                ->url(fn () => Documents::url('invoice', $this->record->id), shouldOpenInNewTab: true),
            ...InvoiceResource::recordActions(),
            Actions\DeleteAction::make(),
        ];
    }
}
