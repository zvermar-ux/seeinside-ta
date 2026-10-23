<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Booking;
use App\Services\Finance\InvoiceBuilder;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();
        $type = in_array(request('type'), ['proforma', 'invoice'], true) ? request('type') : 'invoice';
        $this->data['type'] = $type;
        // ?booking=ID → prefilled from the booking
        if (request('booking') && ($b = Booking::find(request('booking')))) {
            $d = InvoiceBuilder::fromBooking($b, $type);
            foreach (['booking_id', 'client_id', 'client_name', 'client_address', 'client_tax_number', 'currency', 'exchange_rate', 'due_date'] as $k) {
                $this->data[$k] = $d[$k];
            }
            $this->data['lines'] = collect($d['lines'])->mapWithKeys(fn ($l) => [(string) \Illuminate\Support\Str::uuid() => $l + ['total' => $l['price']]])->all();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
