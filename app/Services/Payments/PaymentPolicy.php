<?php

namespace App\Services\Payments;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\OnlinePayment;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * How an offer is paid when it is booked on the website:
 *  mode  none    – nothing paid in advance (the client pays at the agency)
 *        deposit – a part (percent) is paid online with the booking
 *        full    – 100% online with the booking
 *  agency       – the client may choose "pay at the agency" instead of the card
 * Defaults per kind of offer are set on the agency (Online payment), each offer can have its own.
 */
class PaymentPolicy
{
    public const KINDS = ['tour', 'accommodation', 'event', 'visa', 'hotel', 'custom', 'charter'];

    public static function kindOf($item): string
    {
        return match (true) {
            $item instanceof \App\Models\Tour => 'tour',
            $item instanceof \App\Models\Accommodation => 'accommodation',
            $item instanceof \App\Models\Event => 'event',
            $item instanceof \App\Models\VisaType => 'visa',
            $item instanceof \App\Models\Charter => 'charter',
            $item instanceof \App\Models\CustomRecord => 'custom',
            default => 'hotel',
        };
    }

    public static function kindLabels(): array
    {
        return ['tour' => __('Tours'), 'accommodation' => __('Apartments & villas'), 'event' => __('Events'), 'visa' => __('Visas'),
            'hotel' => __('Hotels (API)'), 'custom' => __('Other offers'), 'charter' => __('Charter flights')];
    }

    public static function modes(): array
    {
        return ['none' => __('No advance payment'), 'deposit' => __('Deposit online (part of the amount)'), 'full' => __('100% online')];
    }

    /** @return array{online:bool, mode:string, percent:int, agency:bool} */
    public static function for(?Agency $agency, $item = null, ?string $kind = null): array
    {
        $kind ??= $item ? self::kindOf($item) : 'hotel';
        $def = (array) (Halk::settings($agency)['rules'][$kind] ?? []);
        $own = $item instanceof Model ? (array) ($item->getAttribute('payment_policy') ?? []) : [];
        $mode = ($own['mode'] ?? 'default') !== 'default' ? $own['mode'] : ($def['mode'] ?? 'none');
        $percent = (int) (($own['mode'] ?? 'default') !== 'default' && ! empty($own['percent']) ? $own['percent'] : ($def['percent'] ?? 30));
        $agencyOk = ($own['agency'] ?? 'default') !== 'default' ? $own['agency'] === 'yes' : (bool) ($def['agency'] ?? true);
        $online = Halk::enabled($agency) && in_array($mode, ['deposit', 'full'], true);
        if ($item instanceof \App\Models\VisaType && ! $item->online_payment && ($own['mode'] ?? 'default') === 'default') {
            $online = false;   // the visa switch "Online payment" still decides when the visa has no own rule
        }

        return [
            'online' => $online,
            'mode' => $online ? $mode : 'none',
            'percent' => $online ? ($mode === 'full' ? 100 : max(1, min(100, $percent))) : 0,
            'agency' => ! $online || $agencyOk,   // without online payment the agency is the only way
        ];
    }

    /** What the website needs to show the payment choice */
    public static function site(?Agency $agency, $item): array
    {
        $p = self::for($agency, $item);
        $s = Halk::settings($agency);

        return $p + ['provider' => $p['online'] ? 'halk' : null, 'title' => $s['title'] ?? null, 'note' => $s['note'] ?? null];
    }

    public static function due(?float $total, array $p): ?float
    {
        if (! $p['online'] || ! $total || $total <= 0) {
            return null;
        }

        return round($total * $p['percent'] / 100, 2);
    }

    /** A new attempt on the bank's page for an amount in the currency of the booking (paid in MKD) */
    public static function start(Booking|VisaApplication $payable, float $amount, ?string $returnUrl = null): OnlinePayment
    {
        $agency = Agency::find($payable->agency_id);
        $currency = $payable->currency ?: 'EUR';
        $mkd = $currency === 'MKD' ? $amount : round($agency->toBase($amount, $currency, $payable instanceof Booking && $payable->exchange_rate ? (float) $payable->exchange_rate : null));
        $n = OnlinePayment::withoutGlobalScopes()->where('payable_type', $payable->getMorphClass())->where('payable_id', $payable->id)->count() + 1;

        return OnlinePayment::withoutGlobalScopes()->create([
            'agency_id' => $payable->agency_id,
            'payable_type' => $payable->getMorphClass(), 'payable_id' => $payable->id,
            'token' => Str::random(48),
            'oid' => Str::limit(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $payable->reference), 40, '').'-'.$n.'-'.Str::upper(Str::random(4)),
            'provider' => 'halk',
            'amount' => $mkd, 'currency' => 'MKD', 'booking_amount' => round($amount, 2),
            'status' => 'pending',
            'return_url' => $returnUrl ? mb_substr($returnUrl, 0, 500) : null,
        ]);
    }

    /** The return address is used only when it is on one of the agency's websites (no open redirect) */
    public static function safeReturn(int $agencyId, ?string $url): ?string
    {
        if (! $url || ! preg_match('#^https?://[^\s"<>]+$#i', $url)) {
            return null;
        }
        $norm = fn ($h) => preg_replace('/^www\./', '', strtolower((string) $h));
        $host = $norm(parse_url($url, PHP_URL_HOST));
        $hosts = \App\Models\ApiClient::withoutGlobalScopes()->where('agency_id', $agencyId)->get(['site_url', 'webhook_url'])
            ->flatMap(fn ($c) => [$norm(parse_url((string) $c->site_url, PHP_URL_HOST)), $norm(parse_url((string) $c->webhook_url, PHP_URL_HOST))])->filter()->all();

        return $host && in_array($host, $hosts, true) ? mb_substr($url, 0, 500) : null;
    }

    /**
     * The client's choice on the website (card | agency) checked against the rule of the offer.
     * @return string|null "card", "agency" or null (no online payment for this offer)
     */
    public static function choice(array $p, ?string $method, ?float $total): ?string
    {
        if (! $p['online']) {
            return null;
        }
        if (! $total || $total <= 0) {
            // no price yet (inquiry): nothing to pay online now
            return 'agency';
        }
        if ($method === 'agency' && $p['agency']) {
            return 'agency';
        }
        if ($method === 'agency' && ! $p['agency']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment_method' => __('This offer is paid online with a card when booking.')]);
        }

        return 'card';
    }

    /** After the booking is saved: the payment the website shows / opens */
    public static function respond(Booking|VisaApplication $x, array $p, ?string $choice, ?float $total, ?string $returnUrl): ?array
    {
        if ($choice === null) {
            return null;
        }
        $due = self::due($total, $p);
        $x->forceFill(['payment_choice' => $choice] + ($x instanceof Booking ? ['payment_due_now' => $due] : []))->saveQuietly();
        if ($choice !== 'card' || ! $due) {
            return ['method' => 'agency', 'due' => $due, 'percent' => $p['percent'], 'currency' => $x->currency];
        }
        $op = self::start($x, $due, self::safeReturn((int) $x->agency_id, $returnUrl));

        return ['method' => 'card', 'url' => $op->url(), 'due' => $due, 'percent' => $p['percent'], 'currency' => $x->currency,
            'amount_mkd' => (float) $op->amount];
    }

    /** For the website (meta): is card payment on, and the default rule of each kind of offer */
    public static function meta(?Agency $agency): array
    {
        $s = Halk::settings($agency);
        $rules = [];
        foreach (self::KINDS as $k) {
            $r = (array) ($s['rules'][$k] ?? []);
            $rules[$k] = ['mode' => in_array($r['mode'] ?? 'none', ['none', 'deposit', 'full'], true) ? ($r['mode'] ?? 'none') : 'none',
                'percent' => (int) ($r['percent'] ?? 30), 'agency' => (bool) ($r['agency'] ?? true)];
        }

        return ['enabled' => Halk::enabled($agency), 'provider' => Halk::enabled($agency) ? 'halk' : null,
            'title' => $s['title'] ?? null, 'note' => $s['note'] ?? null, 'rules' => $rules];
    }
}
