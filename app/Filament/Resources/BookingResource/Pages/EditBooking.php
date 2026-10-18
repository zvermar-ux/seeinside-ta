<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /** A payment was added / changed below: show the new paid amount and status in the form */
    #[On('refresh-booking')]
    public function refreshPaid(): void
    {
        $this->record->refresh();
        $this->data['paid_amount'] = $this->record->paid_amount;
        $this->data['payment_status'] = $this->record->payment_status?->value;
    }
}
