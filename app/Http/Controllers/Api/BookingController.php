<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\VisaApplicationStatus;
use App\Models\Accommodation;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Tour;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Booking and visa forms submitted from the website */
class BookingController extends Controller
{
    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // hidden field on website forms: bots fill it
            return response()->json(['data' => ['reference' => 'R0000-00000', 'status' => 'inquiry', 'total' => null, 'currency' => 'EUR', 'payment' => null]], 201);
        }
        $data = $request->validate([
            'type' => 'required|in:tour,event,accommodation,charter,custom',
            'slug' => 'required_unless:type,charter|nullable|string',
            'charter_id' => 'required_if:type,charter|nullable|integer',
            'transport_only' => 'nullable|boolean',
            'departure_id' => 'nullable|integer',
            'departure_place_id' => 'nullable|integer',
            'room_id' => 'nullable|integer',
            'term_id' => 'nullable|integer',
            'ticket_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after:date_from',
            'adults' => 'required|integer|min:0|max:50',
            'children' => 'nullable|integer|min:0|max:20',
            'children_ages' => 'nullable|array|max:20',
            'children_ages.*' => 'integer|min:0|max:17',
            'quantity' => 'nullable|integer|min:1|max:50',
            'extras' => 'nullable|array',
            'extras.*' => 'integer',
            'customer.name' => 'required|string|max:150',
            'customer.email' => 'required|email|max:150',
            'customer.phone' => 'required|string|max:50',
            'customer.city' => 'nullable|string|max:100',
            'passengers' => 'nullable|array|max:50',
            'passengers.*.first_name' => 'nullable|string|max:80',
            'passengers.*.last_name' => 'nullable|string|max:80',
            'passengers.*.birth_date' => 'nullable|date',
            'passengers.*.passport' => 'nullable|string|max:30',
            'passengers.*.phone' => 'nullable|string|max:50',
            'passengers.*.title' => 'nullable|string|max:10',
            'message' => 'nullable|string|max:3000',
            'locale' => 'nullable|in:mk,en',
        ]);

        $agencyId = $this->agencyId($request);
        if ($data['type'] === 'charter') {
            $item = \App\Models\Charter::query()->where('agency_id', $agencyId)->where('is_active', true)->findOrFail($data['charter_id']);
        } else {
            $class = ['tour' => Tour::class, 'event' => Event::class, 'accommodation' => Accommodation::class, 'custom' => \App\Models\CustomRecord::class][$data['type']];
            $item = $class::query()->where('agency_id', $agencyId)->where('is_active', true)->where('slug', $data['slug'])->firstOrFail();
            if ($item instanceof \App\Models\CustomRecord && (! $item->recordType?->bookable || ! Agency::find($agencyId)?->hasModule('custom:'.$item->recordType->slug))) {
                throw ValidationException::withMessages(['slug' => 'Online booking is disabled for this offer.']);
            }
        }

        if (! ($item instanceof \App\Models\Charter) && ! $item->booking_enabled) {
            throw ValidationException::withMessages(['slug' => 'Online booking is disabled for this offer.']);
        }

        $adults = (int) $data['adults'];
        $children = (int) ($data['children'] ?? 0);
        $booking = new Booking([
            'agency_id' => $agencyId,
            'adults' => $adults,
            'children' => $children,
            'children_ages' => $data['children_ages'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 1),
            'customer_name' => $data['customer']['name'],
            'customer_email' => $data['customer']['email'],
            'customer_phone' => $data['customer']['phone'],
            'customer_city' => $data['customer']['city'] ?? null,
            'passengers' => $data['passengers'] ?? null,
            'message' => $data['message'] ?? null,
            'currency' => $item->currency,
            'source' => 'web',
            'locale' => $data['locale'] ?? 'mk',
            'status' => ($item->booking_mode ?? 'request') === 'instant' ? BookingStatus::Pending : BookingStatus::Inquiry,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $booking->bookable()->associate($item);

        $total = null;

        if ($item instanceof \App\Models\Charter) {
            $pax = max(1, $adults + $children);
            if ($item->seatsLeft() !== null && $item->seatsLeft() < $pax) {
                throw ValidationException::withMessages(['adults' => 'Not enough seats left on this charter.']);
            }
            $booking->date_from = $item->departure_date;
            $booking->date_to = $item->return_date;
            $transportOnly = ! empty($data['transport_only']) && $item->transport_only_price !== null;
            $total = ($transportOnly ? (float) $item->transport_only_price : (float) $item->price) * $pax;
            if ($transportOnly) {
                $booking->extras = [['id' => 0, 'name' => 'Само превоз / Transport only', 'price' => (float) $item->transport_only_price, 'price_type' => 'per_person']];
            }
        }

        if ($item instanceof \App\Models\CustomRecord) {
            $booking->date_from = $data['date_from'] ?? null;
            $booking->date_to = $data['date_to'] ?? null;
            $total = $item->price_from !== null ? (float) $item->price_from * \App\Models\CustomRecord::units($item, $booking) : null;
        }

        if ($item instanceof Tour) {
            $dep = $item->departures()->whereKey($data['departure_id'] ?? 0)->first();
            if (! $dep) {
                throw ValidationException::withMessages(['departure_id' => 'Choose a departure date.']);
            }
            if (in_array($dep->status?->value, ['sold_out', 'cancelled'], true)) {
                throw ValidationException::withMessages(['departure_id' => 'This departure is not available.']);
            }
            if ($dep->seatsLeft() !== null && $dep->seatsLeft() < $adults + $children) {
                throw ValidationException::withMessages(['adults' => 'Not enough seats left on this departure.']);
            }
            $booking->tour_departure_id = $dep->id;
            $booking->date_from = $dep->start_date;
            $booking->date_to = $dep->end_date;

            $surcharge = 0;
            if (! empty($data['departure_place_id'])) {
                $link = $item->departurePlaceLinks()->where('departure_place_id', $data['departure_place_id'])->first();
                if (! $link) {
                    throw ValidationException::withMessages(['departure_place_id' => 'Unknown departure place for this tour.']);
                }
                $booking->departure_place_id = $link->departure_place_id;
                $surcharge = (float) $link->surcharge;
            }

            $total = ($dep->adultPrice() ?? 0) * $adults + ($dep->childPrice() ?? $dep->adultPrice() ?? 0) * $children + $surcharge * ($adults + $children);

            $extras = $item->extras()->whereIn('id', $data['extras'] ?? [])->get();
            $booking->extras = $extras->map(fn ($e) => ['id' => $e->id, 'name' => $e->tr('name'), 'price' => (float) $e->price, 'price_type' => $e->price_type])->values()->all();
            foreach ($extras as $e) {
                $total += (float) $e->price * ($e->price_type === 'per_person' ? $adults + $children : 1);
            }
        }

        if ($item instanceof Event) {
            $ticket = $item->tickets()->whereKey($data['ticket_id'] ?? 0)->where('is_active', true)->first();
            if (! $ticket) {
                throw ValidationException::withMessages(['ticket_id' => 'Choose a ticket type.']);
            }
            if ($ticket->available() !== null && $ticket->available() < $booking->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Not enough tickets left.']);
            }
            $booking->event_ticket_id = $ticket->id;
            $booking->date_from = $item->starts_at;
            $total = (float) $ticket->price * $booking->quantity;
        }

        if ($item instanceof Accommodation && $item->book_by_terms) {
            $term = $item->stayTerms()->where('is_active', true)->whereKey($data['term_id'] ?? 0)->first()
                ?? (! empty($data['date_from']) ? $item->stayTerms()->where('is_active', true)->whereDate('date_from', $data['date_from'])->first() : null);
            if (! $term) {
                throw ValidationException::withMessages(['term_id' => 'Choose a term.']);
            }
            $offer = collect($item->termOffers($term, max(1, $adults), $children))->firstWhere('room_id', (int) ($data['room_id'] ?? 0));
            if (! $offer) {
                throw ValidationException::withMessages(['room_id' => 'This room is not available in the selected term.']);
            }
            $booking->stay_term_id = $term->id;
            $booking->accommodation_room_id = $offer['room_id'];
            $booking->date_from = $term->date_from;
            $booking->date_to = $term->date_to;
            $total = $offer['total'] * ($item->term_price_type === 'per_person' ? 1 : max(1, (int) ($data['quantity'] ?? 1)));
        } elseif ($item instanceof Accommodation) {
            if (empty($data['date_from']) || empty($data['date_to'])) {
                throw ValidationException::withMessages(['date_from' => 'Choose check-in and check-out dates.']);
            }
            if (! empty($data['room_id'])) {
                $service = app(\App\Services\Availability\AvailabilityService::class);
                $availability = $service->forAccommodation($item, \Carbon\Carbon::parse($data['date_from']), \Carbon\Carbon::parse($data['date_to'])->subDay(), false);
                $free = $service->freeForStay($availability, (int) $data['room_id'], \Carbon\Carbon::parse($data['date_from'])->toDateString(), \Carbon\Carbon::parse($data['date_to'])->toDateString());
                if ($free !== null && $free < max(1, (int) ($data['quantity'] ?? 1))) {
                    throw ValidationException::withMessages(['room_id' => 'This room is not available for the selected dates.']);
                }
            }
            $offers = collect($item->quote($data['date_from'], $data['date_to'], max(1, $adults), $children));
            $offer = ! empty($data['room_id']) ? $offers->firstWhere('room_id', (int) $data['room_id']) : $offers->first();
            if (! empty($data['room_id']) && ! $offer && ! $item->rooms()->whereKey($data['room_id'])->exists()) {
                throw ValidationException::withMessages(['room_id' => 'Unknown room.']);
            }
            $booking->accommodation_room_id = $offer['room_id'] ?? ($data['room_id'] ?? null);
            $booking->date_from = $data['date_from'];
            $booking->date_to = $data['date_to'];
            $total = $offer['total'] ?? null;   // no price for these dates -> inquiry without price
        }

        $booking->total = $total !== null ? round($total, 2) : null;
        $booking->save();

        // e-mail / WhatsApp / Viber to the client and to the agency (after the response)
        $fresh = $booking->fresh();
        \App\Services\Messaging\Messenger::later(function () use ($fresh) {
            \App\Services\Messaging\Messenger::notify('booking_received', $fresh);
            \App\Services\Messaging\Messenger::notify('new_booking', $fresh);
        });

        return response()->json(['data' => [
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'total' => $booking->total !== null ? (float) $booking->total : null,
            'currency' => $booking->currency,
            'payment' => null,   // online payment link once a payment provider is configured
            'manage_url' => \App\Http\Controllers\PortalController::url($booking),
            'qr_url' => $booking->qrPngUrl(),
        ]], 201);
    }

    /** Status of a booking for the customer (reference + the e-mail used when booking) */
    public function status(Request $request, string $reference): JsonResponse
    {
        $email = (string) $request->query('email');
        $b = Booking::query()->where('agency_id', $this->agencyId($request))->where('reference', $reference)->first();
        if (! $b || ! $email || strcasecmp(trim($b->customer_email), trim($email)) !== 0) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => [
            'reference' => $b->reference,
            'offer' => $b->itemTitle(),
            'type' => $b->bookable_type,
            'date_from' => $b->date_from?->toDateString(),
            'date_to' => $b->date_to?->toDateString(),
            'adults' => $b->adults,
            'children' => $b->children,
            'status' => ['value' => $b->status?->value, 'label' => $b->status?->labels()],
            'payment_status' => ['value' => $b->payment_status?->value, 'label' => $b->payment_status?->labels()],
            'total' => $b->total !== null ? (float) $b->total : null,
            'paid' => (float) $b->paid_amount,
            'currency' => $b->currency,
            'created_at' => $b->created_at?->toIso8601String(),
            'manage_url' => \App\Http\Controllers\PortalController::url($b),
            'qr_url' => $b->qrPngUrl(),
        ]]);
    }

    /** Status of a visa application for the applicant (reference + e-mail) */
    public function visaStatus(Request $request, string $reference): JsonResponse
    {
        $email = (string) $request->query('email');
        $a = VisaApplication::query()->with('visaType')->where('agency_id', $this->agencyId($request))->where('reference', $reference)->first();
        if (! $a || ! $email || strcasecmp(trim($a->email), trim($email)) !== 0) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => [
            'reference' => $a->reference,
            'visa' => $a->visaType ? trim($a->visaType->country.' – '.$a->visaType->tr('name')) : null,
            'name' => trim($a->first_name.' '.$a->last_name),
            'status' => ['value' => $a->status?->value, 'label' => $a->status?->labels()],
            'payment_status' => ['value' => $a->payment_status?->value, 'label' => $a->payment_status?->labels()],
            'amount' => $a->amount !== null ? (float) $a->amount : null,
            'currency' => $a->currency,
            'documents' => count($a->documents ?? []),
            'created_at' => $a->created_at?->toIso8601String(),
        ]]);
    }

    /** Multipart form: visa, first_name, last_name, email, phone, answers[key], documents[key] (file or files[]) */
    public function visaApplication(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // hidden field on website forms: bots fill it
            return response()->json(['data' => ['reference' => 'V0000-00000', 'status' => 'new']], 201);
        }
        $base = $request->validate([
            'visa' => 'required|string',
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:50',
            'nationality' => 'nullable|string|max:80',
            'travel_date' => 'nullable|date',
            'express' => 'nullable|boolean',
            'answers' => 'nullable|array',
            'locale' => 'nullable|in:mk,en',
        ]);

        // name / e-mail / phone are asked once at the top of website forms; questionnaires may contain them too
        $answers = (array) $request->input('answers', []);
        foreach (['first_name', 'last_name', 'email', 'phone'] as $k) {
            if (! isset($answers[$k]) && $request->filled($k)) {
                $answers[$k] = $request->input($k);
            }
        }
        $request->merge(['answers' => $answers]);
        $base['answers'] = $answers;

        $agencyId = $this->agencyId($request);
        $visa = VisaType::query()->where('agency_id', $agencyId)->where('is_active', true)->where('slug', $base['visa'])->firstOrFail();

        // Questionnaire and documents defined for this visa type
        $rules = [];
        foreach ($visa->questionnaire ?? [] as $q) {
            $rule = [! empty($q['required']) ? 'required' : 'nullable'];
            $rule[] = match ($q['type'] ?? 'text') {
                'email' => 'email',
                'date' => 'date',
                'number' => 'numeric',
                'select' => 'in:'.implode(',', array_map(fn ($o) => str_replace(',', '', $o), $q['options'] ?? [])),
                'checkbox', 'radio' => 'string',
                default => 'string|max:5000',
            };
            $rules['answers.'.$q['key']] = implode('|', $rule);
        }
        foreach ($visa->required_documents ?? [] as $d) {
            $file = 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,heic';
            if (! empty($d['multiple'])) {
                $rules['documents.'.$d['key']] = (! empty($d['required']) ? 'required' : 'nullable').'|array|max:10';
                $rules['documents.'.$d['key'].'.*'] = $file;
            } else {
                $rules['documents.'.$d['key']] = (! empty($d['required']) ? 'required' : 'nullable').'|'.$file;
            }
        }
        $request->validate($rules);

        $application = new VisaApplication([
            'agency_id' => $agencyId,
            'visa_type_id' => $visa->id,
            'first_name' => $base['first_name'],
            'last_name' => $base['last_name'],
            'email' => $base['email'],
            'phone' => $base['phone'],
            'nationality' => $base['nationality'] ?? null,
            'travel_date' => $base['travel_date'] ?? null,
            'is_express' => (bool) ($base['express'] ?? false),
            'answers' => Arr::only((array) ($base['answers'] ?? []), collect($visa->questionnaire ?? [])->pluck('key')->all()),
            'status' => VisaApplicationStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'currency' => $visa->currency,
            'source' => 'web',
            'locale' => $base['locale'] ?? 'mk',
        ]);
        $application->amount = $visa->totalPrice($application->is_express);
        $application->save();

        // Files go to the private disk (never publicly reachable)
        $docs = [];
        foreach ($visa->required_documents ?? [] as $d) {
            $files = $request->file('documents.'.$d['key']);
            foreach (array_filter(is_array($files) ? $files : [$files]) as $file) {
                $docs[] = [
                    'key' => $d['key'],
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store("visa-documents/{$agencyId}/{$application->reference}", 'local'),
                ];
            }
        }
        $application->update(['documents' => $docs]);

        $this->notifyAgency($agencyId, 'Ново барање за виза '.$application->reference, implode("\n", [
            'Виза: '.$visa->country.' — '.$visa->tr('name'),
            'Апликант: '.$application->fullName().' · '.$application->phone.' · '.$application->email,
            'Документи: '.count($docs),
        ]));

        return response()->json(['data' => [
            'reference' => $application->reference,
            'status' => $application->status->value,
            'amount' => (float) $application->amount,
            'currency' => $application->currency,
            'payment' => null,
        ]], 201);
    }

    protected function notifyAgency(int $agencyId, string $subject, string $body): void
    {
        $agency = Agency::find($agencyId);
        $to = $agency?->booking_notify_email ?: $agency?->email;
        if (! $to) {
            return;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (Throwable $e) {
            Log::warning('Booking notification e-mail failed: '.$e->getMessage());
        }
    }
}
