<?php

namespace App\Services\Availability;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Services\Availability\Drivers\JsonFeedDriver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Free / booked units per room and night.
 *
 * Local offer: units of the room minus our bookings (inquiries and pending bookings hold the room too).
 * Hotels of an external operator: the free units come from the booking system's API (cached for a few
 * minutes); our own bookings are still shown on top. If the API cannot be reached, local data is used.
 */
class AvailabilityService
{
    /** Drivers per booking system; systems without a ready driver can use the generic JSON feed */
    public static array $drivers = [
        'expert' => JsonFeedDriver::class,
        'other' => JsonFeedDriver::class,
    ];

    /** Booking statuses that occupy a room */
    public const HOLDING = [BookingStatus::Inquiry, BookingStatus::Pending];

    public const BOOKED = [BookingStatus::Confirmed, BookingStatus::Completed];

    /**
     * @return array{source: string, supplier: ?string, notice: ?string, from: string, to: string, dates: string[],
     *               rooms: array<int, array{id:int, name:string, units:int, external:bool, days: array<string, array>}>}
     */
    public function forAccommodation(Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to, bool $withBookings = true): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $dates = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->toDateString())->all();

        $rooms = $accommodation->rooms()->with('rates')->orderBy('sort_order')->get();

        $bookings = Booking::withoutGlobalScopes()
            ->whereIn('accommodation_room_id', $rooms->pluck('id'))
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->whereDate('date_from', '<=', $to)
            ->whereDate('date_to', '>', $from)
            ->get();

        [$supplierData, $source, $notice, $supplierName] = $this->supplier($accommodation, $from, $to);

        $out = [];
        foreach ($rooms as $room) {
            $units = max(1, (int) ($room->units ?: 1));
            $external = $supplierData !== null && filled($room->provider_code) && array_key_exists((string) $room->provider_code, $supplierData);
            $days = [];
            foreach ($dates as $date) {
                // A booking occupies the nights from check-in up to (not including) check-out
                $night = $bookings->filter(fn (Booking $b) => $b->accommodation_room_id === $room->id
                    && $b->date_from && $b->date_from->toDateString() <= $date
                    && ($b->date_to ? $b->date_to->toDateString() > $date : $b->date_from->toDateString() === $date));
                $booked = $night->filter(fn ($b) => in_array($b->status, self::BOOKED, true))->sum(fn ($b) => max(1, (int) $b->quantity));
                $held = $night->filter(fn ($b) => in_array($b->status, self::HOLDING, true))->sum(fn ($b) => max(1, (int) $b->quantity));

                $rate = $room->rates->first(fn ($r) => $date >= $r->date_from->toDateString() && $date <= $r->date_to->toDateString());
                $price = $rate?->price ?? $room->price;

                $free = $external
                    ? (int) ($supplierData[(string) $room->provider_code][$date] ?? 0)
                    : max(0, $units - $booked - $held);

                $days[$date] = [
                    'free' => $free,
                    'booked' => $booked,
                    'held' => $held,
                    'units' => $external ? max($free, $units) : $units,
                    'price' => $price !== null ? (float) $price : null,
                    'on_request' => $price === null,
                    'bookings' => $withBookings ? $night->map(fn ($b) => [
                        'id' => $b->id, 'reference' => $b->reference, 'customer' => $b->customer_name,
                        'status' => $b->status?->value, 'from' => $b->date_from?->toDateString(), 'to' => $b->date_to?->toDateString(),
                    ])->values()->all() : [],
                ];
            }

            $out[$room->id] = [
                'id' => $room->id,
                'name' => $room->tr('name'),
                'board' => $room->board?->value,
                'units' => $units,
                'external' => $external,
                'days' => $days,
            ];
        }

        return [
            'source' => $source,
            'supplier' => $supplierName,
            'notice' => $notice,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'dates' => $dates,
            'rooms' => $out,
        ];
    }

    /** Minimum free units of a room over the nights from check-in to check-out (null = room unknown) */
    public function freeForStay(array $availability, int $roomId, string $from, string $to): ?int
    {
        $room = $availability['rooms'][$roomId] ?? null;
        if (! $room) {
            return null;
        }
        $nights = collect($room['days'])->filter(fn ($d, $date) => $date >= $from && $date < $to);

        return $nights->isEmpty() ? null : (int) $nights->min('free');
    }

    /** @return array{0: ?array, 1: string, 2: ?string, 3: ?string} */
    protected function supplier(Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to): array
    {
        $connection = $accommodation->supplierConnection;
        if (! $connection || ! $accommodation->use_supplier_availability || blank($accommodation->provider_code)) {
            return [null, 'local', null, null];
        }

        $name = $connection->name;
        if (! $connection->is_active) {
            return [null, 'local', __('The connection to :name is inactive – showing our own bookings only.', ['name' => $name]), $name];
        }

        $driver = static::$drivers[$connection->provider?->value] ?? null;
        if (! $driver) {
            return [null, 'local', __('Live availability from :name will work once the API connection is completed – showing our own bookings for now.', ['name' => $name]), $name];
        }

        try {
            $key = 'availability:'.$connection->id.':'.$accommodation->provider_code.':'.$from->toDateString().':'.$to->toDateString();
            $data = Cache::remember($key, now()->addMinutes(5), fn () => app($driver)->availability($connection, $accommodation, $from, $to));
            $connection->forceFill(['last_sync_at' => now()])->saveQuietly();

            return [$data, 'supplier', null, $name];
        } catch (Throwable $e) {
            report($e);

            $error = $e instanceof \Illuminate\Http\Client\RequestException
                ? 'HTTP '.$e->response->status()
                : ($e instanceof \Illuminate\Http\Client\ConnectionException ? __('no connection') : mb_substr(strtok($e->getMessage(), "\n"), 0, 120));

            return [null, 'local', __(':name did not answer (:error) – showing our own bookings only.', ['name' => $name, 'error' => $error]), $name];
        }
    }
}
