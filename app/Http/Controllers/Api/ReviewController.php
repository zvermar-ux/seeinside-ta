<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Review;
use App\Models\VisaApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Reviews: website visitors write them, the agency approves them, the website shows the approved ones */
class ReviewController extends Controller
{
    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    /** All approved reviews of the agency (the website keeps the whole list) */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => static::items($this->agencyId($request))]);
    }

    public static function items(int $agencyId): array
    {
        return Review::withoutGlobalScopes()->with(['reviewable' => fn ($q) => $q->withoutGlobalScopes()])->where('agency_id', $agencyId)->approved()
            ->orderByDesc('is_featured')->orderByDesc('approved_at')->orderByDesc('id')->limit(1000)->get()
            ->map(function (Review $r) {
                $item = $r->reviewable;
                $type = $r->websiteItemType();

                return [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'body' => $r->body,
                    'reply' => $r->reply,
                    'name' => $r->name,
                    'city' => $r->city,
                    'trip_date' => $r->trip_date,
                    'locale' => $r->locale,
                    'is_featured' => $r->is_featured,
                    'verified' => (bool) $r->verified,
                    'item_label' => $r->reviewable_id ? null : $r->item_label,
                    'date' => ($r->approved_at ?? $r->created_at)?->toDateString(),
                    'item' => $item && $type ? [
                        'type' => $type,
                        'id' => $item->getKey(),
                        'slug' => $item->slug,
                        'title' => method_exists($item, 'trAll') ? $item->trAll($type === 'visa' ? 'name' : 'title') : null,
                        'active' => (bool) $item->is_active,
                    ] : null,
                ];
            })->values()->all();
    }

    /**
     * Proof that the client travelled with the agency: booking (or visa application) number + its e-mail.
     * Returns what the review is about – the exact tour / apartment / event / visa of the booking.
     */
    protected function proof(Request $request): array
    {
        $d = $request->validate(['reference' => 'required|string|max:40', 'email' => 'required|email|max:150']);
        $agencyId = $this->agencyId($request);
        $ref = strtoupper(trim($d['reference']));
        $same = fn (?string $e) => $e && strcasecmp(trim($e), trim($d['email'])) === 0;
        $fail = fn () => throw ValidationException::withMessages(['reference' => __('No booking with this number and e-mail. Check the booking number (e.g. R2611-ABCDE) and the e-mail used when booking.')]);

        $b = Booking::withoutGlobalScopes()->with(['bookable' => fn ($q) => $q->withoutGlobalScopes()])->where('agency_id', $agencyId)->where('reference', $ref)->first();
        if ($b) {
            if (! $same($b->customer_email)) {
                $fail();
            }
            if ($b->status === BookingStatus::Cancelled) {
                throw ValidationException::withMessages(['reference' => __('This booking was cancelled – a review can be written only for a trip that took place.')]);
            }
            $item = $b->bookable;
            $type = $item ? array_search($item::class, Review::TYPES, true) : null;

            return ['booking' => $b, 'visa' => null, 'item' => $type ? $item : null, 'type' => $type ?: ($b->isSupplierHotel() ? 'hotel' : null),
                'title' => trim((string) $b->itemTitle(), ' –'), 'name' => $b->customer_name,
                'date' => $b->date_from?->locale(app()->getLocale() === 'en' ? 'en' : 'mk')->isoFormat('MMMM YYYY')];
        }
        $v = VisaApplication::withoutGlobalScopes()->with(['visaType' => fn ($q) => $q->withoutGlobalScopes()])->where('agency_id', $agencyId)->where('reference', $ref)->first();
        if (! $v || ! $same($v->email)) {
            $fail();
        }
        $vt = $v->visaType;
        $name = $vt ? (is_array($vt->name) ? ($vt->name['mk'] ?? reset($vt->name)) : $vt->name) : null;

        return ['booking' => null, 'visa' => $v, 'item' => $vt, 'type' => $vt ? 'visa' : null,
            'title' => trim(($vt?->country ? $vt->country.' – ' : '').($name ?? '')) ?: __('Visa'), 'name' => $v->fullName(),
            'date' => $v->travel_date?->locale('mk')->isoFormat('MMMM YYYY')];
    }

    protected function alreadyReviewed(array $p): bool
    {
        return Review::withoutGlobalScopes()->where('status', '!=', Review::REJECTED)
            ->where(fn ($q) => $p['booking'] ? $q->where('booking_id', $p['booking']->id) : $q->where('visa_application_id', $p['visa']->id))->exists();
    }

    /** Step 1 on the website: check the booking, then the review fields open for that offer */
    public function verify(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Not found.'], 422);
        }
        $p = $this->proof($request);
        if ($this->alreadyReviewed($p)) {
            return response()->json(['message' => __('You already wrote a review for this booking – thank you!'), 'errors' => ['reference' => [__('You already wrote a review for this booking – thank you!')]]], 422);
        }

        return response()->json(['data' => ['name' => $p['name'], 'item' => $p['title'], 'type' => $p['type'], 'trip_date' => $p['date']]]);
    }

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // hidden field: bots fill it
            return response()->json(['data' => ['status' => Review::PENDING]], 201);
        }
        $p = $this->proof($request);
        if ($this->alreadyReviewed($p)) {
            throw ValidationException::withMessages(['reference' => __('You already wrote a review for this booking – thank you!')]);
        }
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
            'body' => 'required|string|min:10|max:5000',
            'trip_date' => 'nullable|string|max:40',
            'page_url' => 'nullable|url|max:500',
            'locale' => 'nullable|in:mk,en',
        ]);
        $agencyId = $this->agencyId($request);
        $item = $p['item'];

        $review = new Review([
            'agency_id' => $agencyId,
            'booking_id' => $p['booking']?->id,
            'visa_application_id' => $p['visa']?->id,
            'item_label' => mb_substr((string) $p['title'], 0, 255) ?: null,
            'verified' => true,
            'name' => strip_tags($data['name']),
            'email' => $p['booking']?->customer_email ?? $p['visa']?->email,
            'city' => isset($data['city']) ? strip_tags($data['city']) : null,
            'rating' => (int) $data['rating'],
            'title' => isset($data['title']) ? strip_tags($data['title']) : null,
            'body' => strip_tags($data['body']),
            'trip_date' => isset($data['trip_date']) ? strip_tags($data['trip_date']) : $p['date'],
            'page_url' => $data['page_url'] ?? null,
            'locale' => $data['locale'] ?? 'mk',
            'status' => Review::PENDING,
            'source' => 'web',
            'ip' => $request->header('X-Visitor-Ip') ?: $request->ip(),
        ]);
        if ($item) {
            $review->reviewable()->associate($item);
        }
        $review->save();

        $agency = Agency::find($agencyId);
        $to = $agency?->booking_notify_email ?: $agency?->email;
        if ($to) {
            try {
                Mail::raw(
                    "Нова рецензија ({$review->rating}/5) од {$review->name} за „".($review->itemTitle() ?? '—')."“ (резервација ".($p['booking']?->reference ?? $p['visa']?->reference)."):\n\n{$review->body}\n\nОдобри ја во SeeInside → Рецензии: ".url('/admin/reviews'),
                    fn ($m) => $m->to($to)->subject('Нова рецензија чека одобрување')
                );
            } catch (Throwable $e) {
                Log::warning('Review notification failed: '.$e->getMessage());
            }
        }

        return response()->json(['data' => ['status' => Review::PENDING]], 201);
    }
}
