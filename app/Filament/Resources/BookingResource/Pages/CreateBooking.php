<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /** Quick booking from the availability calendar: offer, room and dates come in the URL */
    protected function fillForm(): void
    {
        parent::fillForm();

        $prefill = collect(request()->only(['bookable_type', 'bookable_id', 'accommodation_room_id', 'date_from', 'date_to']))
            ->filter(fn ($v) => filled($v) && is_scalar($v));
        if ($prefill->isEmpty() || ! array_key_exists($prefill->get('bookable_type', 'accommodation'), BookingResource::TYPES)) {
            return;
        }

        $class = BookingResource::TYPES[$prefill->get('bookable_type', 'accommodation')];
        $agencyId = $prefill->has('bookable_id') ? $class::query()->whereKey($prefill->get('bookable_id'))->value('agency_id') : null;

        $this->form->fill(array_merge($this->form->getRawState(), $prefill->all(), array_filter(['agency_id' => $agencyId])));
    }
}
