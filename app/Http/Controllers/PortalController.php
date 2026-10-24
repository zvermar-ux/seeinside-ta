<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Review;
use App\Services\Documents\Documents;
use App\Services\Messaging\Messenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Client portal ("Мојата резервација"), like "Manage booking" on Booking.com:
 * a private link per booking – details, documents, payments, messages with the agency, cancellation request, review.
 */
class PortalController extends Controller
{
    public static function url(Booking $b): string
    {
        if (! $b->access_token && $b->exists) {
            // another copy of the booking may already have one
            $token = Booking::withoutGlobalScopes()->whereKey($b->id)->value('access_token');
            if (! $token) {
                $token = Str::random(40);
                Booking::withoutGlobalScopes()->whereKey($b->id)->whereNull('access_token')->update(['access_token' => $token]);
                $token = Booking::withoutGlobalScopes()->whereKey($b->id)->value('access_token');
            }
            $b->setAttribute('access_token', $token);
            $b->syncOriginalAttribute('access_token');
        }

        return route('portal.show', $b->access_token);
    }

    protected function booking(string $token): Booking
    {
        abort_unless(strlen($token) >= 30, 404);

        return Booking::withoutGlobalScopes()->where('access_token', $token)->with(['bookable', 'room', 'departure', 'payments', 'stayTerm'])->firstOrFail();
    }

    protected function locale(Request $r, Booking $b): string
    {
        $l = in_array($r->query('lang'), ['mk', 'en'], true) ? $r->query('lang') : ($b->locale === 'en' ? 'en' : 'mk');
        app()->setLocale($l);

        return $l;
    }

    public function show(Request $request, string $token): Response
    {
        $b = $this->booking($token);
        $lang = $this->locale($request, $b);
        $agency = Agency::findOrFail($b->agency_id);
        // the client has seen the agency's messages
        Message::withoutGlobalScopes()->where('booking_id', $b->id)->where('direction', 'out')->where('channel', 'portal')->whereNull('read_at')->update(['read_at' => now()]);
        $messages = Message::withoutGlobalScopes()->where('booking_id', $b->id)
            ->where(fn ($q) => $q->where('channel', 'portal')->orWhere(fn ($q) => $q->where('direction', 'out')->whereNotNull('event')->where('event', 'not like', 'agency:%')->where('status', 'sent')))
            ->orderBy('created_at')->get()
            ->unique(fn ($m) => $m->event ? $m->event.'|'.$m->created_at->format('YmdHi') : 'm'.$m->id);   // one notification sent over 3 channels = one entry
        $invoices = Invoice::withoutGlobalScopes()->where('booking_id', $b->id)->where('status', '!=', 'draft')->orderBy('issue_date')->get();
        $ended = ($b->date_to ?? $b->departure?->end_date ?? $b->date_from)?->isPast();
        $reviewed = Review::withoutGlobalScopes()->where('agency_id', $b->agency_id)->where('email', $b->customer_email)->where('created_at', '>=', $b->created_at)->exists();

        return response()->view('portal.booking', [
            'b' => $b, 'agency' => $agency, 'lang' => $lang, 'token' => $token, 'messages' => $messages, 'invoices' => $invoices,
            'canCancel' => ! $b->cancel_requested_at && in_array($b->status, [BookingStatus::Inquiry, BookingStatus::Pending, BookingStatus::Confirmed], true) && ! $ended,
            'canEditPax' => in_array($b->status, [BookingStatus::Inquiry, BookingStatus::Pending, BookingStatus::Confirmed], true) && ! $ended,
            'canReview' => $ended && $b->status !== BookingStatus::Cancelled && ! $reviewed,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function message(Request $request, string $token): RedirectResponse
    {
        $b = $this->booking($token);
        $this->locale($request, $b);
        $data = $request->validate(['body' => 'required|string|min:2|max:3000']);
        Message::withoutGlobalScopes()->create([
            'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'client_id' => $b->client_id, 'direction' => 'in', 'channel' => 'portal',
            'recipient' => null, 'body' => strip_tags($data['body']), 'status' => 'received',
        ]);
        Messenger::later(fn () => Messenger::notify('client_message', $b, null, ['{message}' => strip_tags($data['body'])]));

        return redirect()->to(route('portal.show', [$token, 'lang' => $request->query('lang')]).'#messages')->with('ok', __('Message sent. The agency will answer here and by e-mail.'));
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $b = $this->booking($token);
        $this->locale($request, $b);
        $reason = strip_tags((string) $request->input('reason'));
        if (! $b->cancel_requested_at) {
            $b->forceFill(['cancel_requested_at' => now()])->saveQuietly();
            Message::withoutGlobalScopes()->create([
                'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'client_id' => $b->client_id, 'direction' => 'in', 'channel' => 'portal',
                'event' => 'cancel_request', 'body' => '⚠ '.__('Cancellation request').($reason ? ': '.$reason : ''), 'status' => 'received',
            ]);
            Messenger::later(fn () => Messenger::notify('client_message', $b, null, ['{message}' => '⚠ '.__('Cancellation request').($reason ? ': '.$reason : '')]));
        }

        return redirect()->to(route('portal.show', [$token, 'lang' => $request->query('lang')]).'#messages')->with('ok', __('Your cancellation request was sent. The agency will confirm it and inform you about any costs.'));
    }

    /** The client completes names, dates of birth and passports of the passengers */
    public function passengers(Request $request, string $token): RedirectResponse
    {
        $b = $this->booking($token);
        $this->locale($request, $b);
        $data = $request->validate([
            'passengers' => 'required|array|max:50',
            'passengers.*.first_name' => 'required|string|max:80',
            'passengers.*.last_name' => 'required|string|max:80',
            'passengers.*.birth_date' => 'required|date|before:tomorrow',
            'passengers.*.passport' => 'nullable|string|max:30',
            'passengers.*.phone' => 'nullable|string|max:50',
        ]);
        $old = array_values((array) $b->passengers);
        $rows = [];
        foreach (array_values($data['passengers']) as $k => $p) {
            $rows[] = array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $p) + array_intersect_key((array) ($old[$k] ?? []), array_flip(['client_id', 'title', 'category', 'gender', 'city', 'address']));
        }
        $b->passengers = $rows;
        $b->save();
        Message::withoutGlobalScopes()->create([
            'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'client_id' => $b->client_id, 'direction' => 'in', 'channel' => 'portal',
            'event' => 'passengers_updated', 'body' => __('The client updated the passenger details.'), 'status' => 'received',
        ]);

        return redirect()->to(route('portal.show', [$token, 'lang' => $request->query('lang')]).'#passengers')->with('ok', __('Passenger details saved.'));
    }

    public function review(Request $request, string $token): RedirectResponse
    {
        $b = $this->booking($token);
        $this->locale($request, $b);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'title' => 'nullable|string|max:200', 'body' => 'required|string|min:10|max:5000']);
        $review = new Review([
            'agency_id' => $b->agency_id, 'name' => $b->customer_name, 'email' => $b->customer_email, 'city' => $b->customer_city,
            'rating' => (int) $data['rating'], 'title' => isset($data['title']) ? strip_tags($data['title']) : null, 'body' => strip_tags($data['body']),
            'trip_date' => ($b->date_from ?? $b->departure?->start_date)?->format('m.Y'), 'locale' => $b->locale ?: 'mk',
            'status' => Review::PENDING, 'source' => 'portal', 'ip' => $request->ip(),
        ]);
        if ($b->bookable && in_array($b->bookable_type, ['tour', 'event', 'accommodation'], true)) {
            $review->reviewable()->associate($b->bookable);
        }
        $review->save();

        return redirect()->to(route('portal.show', [$token, 'lang' => $request->query('lang')]))->with('ok', __('Thank you for your review!'));
    }

    public function document(Request $request, string $token, string $type, ?int $id = null): Response
    {
        $b = $this->booking($token);
        $this->locale($request, $b);
        $html = match ($type) {
            'contract' => Documents::contract($b),
            'voucher' => in_array($b->status, [BookingStatus::Confirmed, BookingStatus::Completed], true) ? Documents::voucher($b) : abort(404),
            'invoice' => Documents::invoice(Invoice::withoutGlobalScopes()->where('booking_id', $b->id)->where('status', '!=', 'draft')->findOrFail($id)),
            default => abort(404),
        };

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
