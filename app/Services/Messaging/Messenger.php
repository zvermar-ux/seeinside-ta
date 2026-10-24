<?php

namespace App\Services\Messaging;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Message;
use App\Services\Mail\AgencyMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/** Sends notifications to clients and to the agency over e-mail, WhatsApp and Viber, and logs every message */
class Messenger
{
    public const CHANNELS = ['email', 'whatsapp', 'viber'];

    /** Channels switched on for an event (agency settings; e-mail on by default for client events) */
    public static function channelsFor(Agency $a, string $event): array
    {
        $saved = $a->msg('events.'.$event);
        $default = in_array($event, ['booking_received', 'booking_confirmed', 'payment_reminder', 'invoice_reminder', 'option_expiring', 'agency_reply', 'new_booking', 'client_message', 'reminders_digest'], true) ? ['email'] : [];
        $chosen = $saved === null ? $default : (array) $saved;

        return array_values(array_filter($chosen, fn ($c) => Channels::enabled($a, $c)));
    }

    /**
     * Automatic notification about a booking / invoice. Returns the number of messages sent.
     * $once: skip when the same event was already sent for this booking / invoice.
     */
    public static function notify(string $event, ?Booking $b = null, ?Invoice $i = null, array $extra = [], bool $once = false, ?array $channels = null, ?Agency $agency = null): int
    {
        $agencyId = $b?->agency_id ?? $i?->agency_id ?? $agency?->id;
        $a = $agency ?? ($agencyId ? Agency::find($agencyId) : null);
        if (! $a) {
            return 0;
        }
        if ($once && Message::withoutGlobalScopes()->where('event', $event)->where('status', 'sent')
            ->when($b, fn ($q) => $q->where('booking_id', $b->id))->when(! $b && $i, fn ($q) => $q->where('invoice_id', $i->id))->exists()) {
            return 0;
        }
        $channels ??= self::channelsFor($a, $event);
        if (! $channels) {
            return 0;
        }
        $locale = $b?->locale === 'en' ? 'en' : 'mk';
        [$subject, $text] = Templates::for($a, $event, $locale);
        $vars = Templates::vars($a, $b, $i, $extra);
        $subject = Templates::render($subject, $vars);
        $text = Templates::render($text, $vars);

        if ((Templates::events()[$event][1] ?? 'client') === 'agency') {
            return self::toAgency($a, $event, $subject, $text, $channels, $b);
        }

        $email = $b?->customer_email ?: $b?->client?->email ?: $i?->client?->email;
        $phone = $b?->customer_phone ?: $b?->client?->phone ?: $i?->client?->phone;
        $n = 0;
        foreach ($channels as $ch) {
            $n += (int) self::send($a, $ch, $ch === 'email' ? $email : $phone, $subject, $text, ['event' => $event, 'booking' => $b, 'invoice' => $i]);
        }

        return $n;
    }

    protected static function toAgency(Agency $a, string $event, string $subject, string $text, array $channels, ?Booking $b): int
    {
        $n = 0;
        foreach ($channels as $ch) {
            $to = $ch === 'email' ? ($a->booking_notify_email ?: $a->email) : $a->msg('agency_phone', $a->phone);
            $n += (int) self::send($a, $ch, $to, $subject, $text, ['event' => $event, 'booking' => $b, 'internal' => true]);
        }

        return $n;
    }

    /** One message over one channel; logged in "messages" (also when it fails) */
    public static function send(Agency $a, string $channel, ?string $to, string $subject, string $text, array $ctx = []): bool
    {
        /** @var Booking|null $b */
        $b = $ctx['booking'] ?? null;
        $i = $ctx['invoice'] ?? null;
        $ok = false;
        $ref = null;
        $error = null;
        $recipient = $channel === 'email' ? trim((string) $to) : Channels::phone($to, $a);
        if (! $recipient) {
            $error = $channel === 'email' ? __('No e-mail address.') : __('No phone number.');
        } elseif ($channel === 'email') {
            $html = ($ctx['event'] ?? null) === 'booking_received' && $b && ! ($ctx['internal'] ?? false)
                ? view('emails.booking-confirmation', ['booking' => $b->fresh(['room', 'bookable']), 'agency' => $a, 'portal' => \App\Http\Controllers\PortalController::url($b)])->render()
                : self::emailHtml($a, $text, $b, (bool) ($ctx['internal'] ?? false));
            $error = AgencyMail::send($a, $recipient, $subject, $html, [], $ctx['attachments'] ?? []);
            $ok = $error === null;
        } else {
            [$ok, $res] = Channels::send($a, $channel, $recipient, $text);
            $ok ? $ref = $res : $error = $res;
        }
        try {
            Message::withoutGlobalScopes()->create([
                'agency_id' => $a->id, 'booking_id' => $b?->id, 'invoice_id' => $i?->id, 'client_id' => $b?->client_id ?? $i?->client_id,
                'direction' => 'out', 'channel' => $channel, 'event' => ($ctx['internal'] ?? false) ? 'agency:'.($ctx['event'] ?? '') : ($ctx['event'] ?? null),
                'recipient' => $recipient ?: (string) $to, 'subject' => $subject, 'body' => $text,
                'status' => $ok ? 'sent' : 'failed', 'error' => $error ? mb_substr($error, 0, 1000) : null, 'provider_ref' => $ref,
                'created_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Message log failed: '.$e->getMessage());
        }

        return $ok;
    }

    /** Text message as a branded e-mail with a button to the client portal */
    public static function emailHtml(Agency $a, string $text, ?Booking $b = null, bool $internal = false): string
    {
        return view('emails.message', [
            'agency' => $a, 'text' => $text, 'booking' => $b, 'internal' => $internal,
            'portal' => $b && ! $internal ? \App\Http\Controllers\PortalController::url($b) : null,
        ])->render();
    }

    /** Run after the response (no queue worker needed); never breaks the request */
    public static function later(\Closure $fn): void
    {
        $run = function () use ($fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::warning('Notification failed: '.$e->getMessage());
            }
        };
        app()->runningInConsole() ? $run() : app()->terminating($run);
    }
}
