<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\PhotoStory;
use App\Services\Media\PhotoResizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photo stories: a client with a booking number shares photos of the trip, the agency approves them,
 * the website shows the approved ones (homepage slider and the page "Фотоприказни").
 */
class PhotoStoryController extends Controller
{
    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => static::items($this->agencyId($request))]);
    }

    public static function items(int $agencyId): array
    {
        $url = fn (?string $p) => $p ? Storage::disk('public')->url($p) : null;

        return PhotoStory::withoutGlobalScopes()->where('agency_id', $agencyId)->approved()
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('approved_at')->orderByDesc('id')->limit(500)->get()
            ->map(fn (PhotoStory $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'destination' => $s->destination,
                'trip_date' => $s->trip_date,
                'story' => $s->story,
                'is_featured' => $s->is_featured,
                'date' => ($s->approved_at ?? $s->created_at)?->toDateString(),
                'photos' => collect((array) $s->photos)->filter(fn ($p) => is_array($p) && ! empty($p['path']))
                    ->map(fn ($p) => ['url' => $url($p['path']), 'thumb' => $url($p['thumb'] ?? $p['path']), 'w' => (int) ($p['w'] ?? 0), 'h' => (int) ($p['h'] ?? 0)])->values()->all(),
            ])->filter(fn ($s) => $s['photos'])->values()->all();
    }

    /** Booking number + e-mail of the booking = proof that the client travelled with the agency */
    protected function booking(Request $request): Booking
    {
        $d = $request->validate(['reference' => 'required|string|max:40', 'email' => 'required|email|max:150']);
        $b = Booking::withoutGlobalScopes()->where('agency_id', $this->agencyId($request))
            ->where('reference', strtoupper(trim($d['reference'])))->first();
        if (! $b || ! $b->customer_email || strcasecmp(trim($b->customer_email), trim($d['email'])) !== 0) {
            throw ValidationException::withMessages(['reference' => __('No booking with this number and e-mail. Check the booking number (e.g. R2611-ABCDE) and the e-mail used when booking.')]);
        }

        return $b;
    }

    public function verify(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Not found.'], 422);
        }
        $b = $this->booking($request);

        return response()->json(['data' => [
            'name' => $b->customer_name,
            'destination' => trim((string) $b->itemTitle()),
            'trip_date' => $b->date_from ? $b->date_from->locale('mk')->isoFormat('MMMM YYYY') : null,
            'max_photos' => 15,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // bots
            return response()->json(['data' => ['status' => PhotoStory::PENDING]], 201);
        }
        $b = $this->booking($request);
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'destination' => 'nullable|string|max:200',
            'trip_date' => 'nullable|string|max:40',
            'story' => 'nullable|string|max:3000',
            'photos' => 'required|array|min:1|max:15',
            'photos.*' => 'file|max:15360|mimes:jpg,jpeg,png,webp',
            'locale' => 'nullable|in:mk,en',
        ]);
        if (PhotoStory::withoutGlobalScopes()->where('booking_id', $b->id)->count() >= 3) {
            throw ValidationException::withMessages(['photos' => __('Photos of this booking were already sent – the agency will publish them after checking.')]);
        }
        $dir = 'photo-stories/'.$b->agency_id.'/'.now()->format('Y-m');
        $photos = [];
        foreach ((array) $request->file('photos') as $file) {
            $photos[] = PhotoResizer::store($file, $dir);
        }
        $s = PhotoStory::withoutGlobalScopes()->create([
            'agency_id' => $b->agency_id, 'booking_id' => $b->id,
            'name' => $d['name'], 'email' => $b->customer_email, 'destination' => $d['destination'] ?? null, 'trip_date' => $d['trip_date'] ?? null,
            'story' => $d['story'] ?? null, 'photos' => $photos, 'status' => PhotoStory::PENDING,
            'locale' => $d['locale'] ?? 'mk', 'ip' => $request->ip(),
        ]);

        // the agency knows there is something to approve
        \App\Services\Messaging\Messenger::later(function () use ($s, $b) {
            $a = \App\Models\Agency::find($s->agency_id);
            if ($a) {
                \App\Services\Messaging\Messenger::send($a, 'email', $a->booking_notify_email ?: $a->email, __('New travel photos to approve').' – '.$s->name,
                    __(':name sent :n photos (:dest, booking :ref). Approve them in Photo stories.', ['name' => $s->name, 'n' => count((array) $s->photos), 'dest' => $s->destination ?: '—', 'ref' => $b->reference])."\n".url('/admin/photo-stories'),
                    ['event' => 'photo_story', 'internal' => true]);
            }
        });

        return response()->json(['data' => ['status' => PhotoStory::PENDING, 'photos' => count($photos)]], 201);
    }
}
