<?php

namespace App\Filament\Resources\CashEntryResource\Pages;

use App\Filament\Resources\CashEntryResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CashEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCashEntry extends CreateRecord
{
    protected static string $resource = CashEntryResource::class;

    /** A receipt / payout for a booking is also a (negative) payment of that booking */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            /** @var CashEntry $entry */
            $entry = parent::handleRecordCreation($data);
            if ($entry->booking_id && ($b = Booking::find($entry->booking_id))) {
                BookingPayment::$withoutCash = true;
                try {
                    $sign = $entry->direction === 'in' ? 1 : -1;
                    $p = BookingPayment::create([
                        'agency_id' => $entry->agency_id, 'booking_id' => $b->id, 'paid_at' => $entry->entry_at->toDateString(), 'method' => $entry->method,
                        'currency' => $entry->currency, 'original_amount' => $sign * (float) $entry->amount, 'exchange_rate' => $entry->exchange_rate,
                        'amount' => $entry->currency === $b->currency ? $sign * (float) $entry->amount : 0,
                        'reference' => $entry->number(), 'note' => $entry->purpose,
                    ]);
                } finally {
                    BookingPayment::$withoutCash = false;
                }
                $entry->forceFill(['booking_payment_id' => $p->id])->saveQuietly();
            }

            return $entry;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
