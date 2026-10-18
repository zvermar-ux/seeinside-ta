<?php

namespace App\Http\Controllers\Api;

use App\Models\Agency;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        return Review::withoutGlobalScopes()->with('reviewable')->where('agency_id', $agencyId)->approved()
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

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {   // hidden field: bots fill it
            return response()->json(['data' => ['status' => Review::PENDING]], 201);
        }
        $data = $request->validate([
            'type' => 'nullable|in:tour,event,accommodation,visa',
            'slug' => 'nullable|string|max:200',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:150',
            'city' => 'nullable|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
            'body' => 'required|string|min:10|max:5000',
            'trip_date' => 'nullable|string|max:40',
            'page_url' => 'nullable|url|max:500',
            'locale' => 'nullable|in:mk,en',
        ]);
        $agencyId = $this->agencyId($request);

        $item = null;
        if (! empty($data['type']) && ! empty($data['slug'])) {
            $class = Review::TYPES[$data['type']];
            $item = $class::withoutGlobalScopes()->where('agency_id', $agencyId)->where('slug', $data['slug'])->first();
        }

        // the same visitor cannot flood the queue
        $recent = Review::withoutGlobalScopes()->where('agency_id', $agencyId)->where('email', $data['email'])
            ->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= 3) {
            return response()->json(['message' => __('Thank you – we already received your review.')], 429);
        }

        $review = new Review([
            'agency_id' => $agencyId,
            'name' => strip_tags($data['name']),
            'email' => $data['email'],
            'city' => isset($data['city']) ? strip_tags($data['city']) : null,
            'rating' => (int) $data['rating'],
            'title' => isset($data['title']) ? strip_tags($data['title']) : null,
            'body' => strip_tags($data['body']),
            'trip_date' => isset($data['trip_date']) ? strip_tags($data['trip_date']) : null,
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
                    "Нова рецензија ({$review->rating}/5) од {$review->name}".($item ? ' за „'.$review->itemTitle().'“' : '').":\n\n{$review->body}\n\nОдобри ја во SeeInside → Рецензии.",
                    fn ($m) => $m->to($to)->subject('Нова рецензија чека одобрување')
                );
            } catch (Throwable $e) {
                Log::warning('Review notification failed: '.$e->getMessage());
            }
        }

        return response()->json(['data' => ['status' => Review::PENDING]], 201);
    }
}
