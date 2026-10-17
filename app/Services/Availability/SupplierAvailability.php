<?php

namespace App\Services\Availability;

use App\Models\Accommodation;
use App\Models\SupplierConnection;
use Carbon\CarbonInterface;

/**
 * A booking-system driver (Hotelbeds, Filos, Expert…) that tells how many units of each room are free.
 *
 * Returns: [room code in the supplier system => ['Y-m-d' => free units, ...], ...]
 * Throw an exception when the supplier cannot be reached; the calendar then falls back to local bookings.
 */
interface SupplierAvailability
{
    public function availability(SupplierConnection $connection, Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to): array;
}
