<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hotels\HotelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Hotels from booking systems (Hotelbeds…) for the website: destinations, live search, hotel page, booking */
class HotelController extends Controller
{
    public function __construct(protected HotelService $hotels) {}

    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    public function destinations(Request $request): JsonResponse
    {
        return response()->json(['data' => HotelService::destinations($this->agencyId($request))]);
    }

    public function suggest(Request $request): JsonResponse
    {
        return response()->json(['data' => HotelService::suggest($this->agencyId($request), (string) $request->query('q', ''))]);
    }

    /** Imported hotels without prices (hotels page before a search) */
    public function catalog(Request $request): JsonResponse
    {
        $f = $request->validate([
            'destination' => 'nullable|string|max:40',
            'country' => 'nullable|string|max:3',
            'zone' => 'nullable|string|max:120',
            'type' => 'nullable|string|max:200',
            'board' => 'nullable|string|max:60',
            'feature' => 'nullable|string|max:200',
            'stars' => 'nullable',
            'q' => 'nullable|string|max:80',
            'page' => 'nullable|integer|min:1|max:500',
            'per_page' => 'nullable|integer|min:1|max:100',
            'ids' => 'nullable|string|max:3000',   // chosen hotels (lists on the website)
        ]);

        return response()->json(['data' => HotelService::catalog($this->agencyId($request), $f)]);
    }

    protected function query(Request $request): array
    {
        $d = $request->validate([
            'from' => 'required|date_format:Y-m-d|after_or_equal:today',
            'to' => 'required|date_format:Y-m-d|after:from',
            'destination' => 'nullable|string|max:40',
            'hotel' => 'nullable|string|max:60',
            'rooms' => 'nullable|array|min:1|max:5',
            'rooms.*.adults' => 'required_with:rooms|integer|min:1|max:8',
            'rooms.*.children_ages' => 'nullable|array|max:4',
            'rooms.*.children_ages.*' => 'integer|min:0|max:17',
            // simple form: one kind of room
            'room_count' => 'nullable|integer|min:1|max:5',
            'adults' => 'nullable|integer|min:1|max:8',
            'ages' => 'nullable|string|max:40',
        ]);
        if (empty($d['rooms'])) {
            $ages = array_values(array_filter(array_map('trim', explode(',', (string) ($d['ages'] ?? ''))), fn ($a) => $a !== ''));
            $d['rooms'] = array_fill(0, (int) ($d['room_count'] ?? 1), ['adults' => (int) ($d['adults'] ?? 2), 'children_ages' => array_map('intval', $ages)]);
        }

        return ['from' => $d['from'], 'to' => $d['to'], 'rooms' => $d['rooms'], 'destination' => $d['destination'] ?? null, 'hotel' => $d['hotel'] ?? null];
    }

    public function search(Request $request): JsonResponse
    {
        $q = $this->query($request);
        if (! $q['destination'] && ! $q['hotel']) {
            return response()->json(['message' => __('Choose a destination or a hotel.'), 'errors' => ['destination' => [__('Choose a destination or a hotel.')]]], 422);
        }

        return response()->json(['data' => $this->hotels->search($this->agencyId($request), $q)]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $q = $request->filled('from') ? $this->query($request) : null;

        return response()->json(['data' => $this->hotels->hotel($this->agencyId($request), $id, $request->query('search_id'), $q)]);
    }

    public function book(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // bots
            return response()->json(['data' => ['reference' => 'R0000-00000', 'status' => 'pending']], 201);
        }
        $d = $request->validate([
            'search_id' => 'required|string|max:40',
            'hotel' => 'required|string|max:60',
            'rate_key' => 'required|string|max:2000',
            'accept_price' => 'nullable|numeric',
            'query' => 'required|array',
            'query.from' => 'required|date_format:Y-m-d',
            'query.to' => 'required|date_format:Y-m-d|after:query.from',
            'query.rooms' => 'required|array|min:1|max:5',
            'query.rooms.*.adults' => 'required|integer|min:1|max:8',
            'query.rooms.*.children_ages' => 'nullable|array|max:4',
            'query.rooms.*.children_ages.*' => 'integer|min:0|max:17',
            'customer.name' => 'required|string|max:150',
            'customer.email' => 'required|email|max:150',
            'customer.phone' => 'required|string|max:50',
            'passengers' => 'nullable|array|max:40',
            'passengers.*.first_name' => 'nullable|string|max:80',
            'passengers.*.last_name' => 'nullable|string|max:80',
            'passengers.*.birth_date' => 'nullable|date',
            'message' => 'nullable|string|max:2000',
            'locale' => 'nullable|in:mk,en',
            'payment_method' => 'nullable|in:card,agency',
            'return_url' => 'nullable|string|max:500',
            'terms_accepted' => 'nullable|boolean',
            'terms_url' => 'nullable|string|max:500',
        ]);
        $policy = \App\Services\Payments\PaymentPolicy::for(\App\Models\Agency::find($this->agencyId($request)), null, 'hotel');
        if ($policy['online'] && ! $policy['agency'] && ($d['payment_method'] ?? 'card') !== 'card') {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment_method' => __('This offer is paid online with a card when booking.')]);
        }
        try {
            $booking = $this->hotels->book($this->agencyId($request), $d);
        } catch (\App\Services\Hotels\PriceChanged $e) {
            return response()->json(['message' => $e->getMessage(), 'price_changed' => true, 'new_price' => $e->price, 'currency' => $e->currency], 409);
        }

        $fresh = $booking->fresh();
        \App\Services\Messaging\Messenger::later(function () use ($fresh) {
            \App\Services\Messaging\Messenger::notify('booking_received', $fresh);
            \App\Services\Messaging\Messenger::notify('new_booking', $fresh);
        });

        return response()->json(['data' => [
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'booked' => $booking->supplier_status === 'booked',
            'total' => (float) $booking->total,
            'currency' => $booking->currency,
            'manage_url' => \App\Http\Controllers\PortalController::url($booking),
            'qr_url' => $booking->qrPngUrl(),
            'payment' => \App\Services\Payments\PaymentPolicy::respond($booking, $policy,
                \App\Services\Payments\PaymentPolicy::choice($policy, $d['payment_method'] ?? null, (float) $booking->total), (float) $booking->total, $d['return_url'] ?? null),
        ]], 201);
    }
}
