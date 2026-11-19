<?php

namespace App\Services\Payments;

use App\Models\Agency;
use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Halk Bank Macedonia – NestPay (Asseco) "3D Pay Hosting" with Hash v3:
 * the client types the card on the bank's page, the bank posts the result back (okUrl / failUrl).
 * The same as the official WooCommerce plugin of the bank (Hash v3, GLOBAL NET).
 */
class Halk
{
    public const LIVE_URL = 'https://epay.halkbank.mk/fim/est3Dgate';

    public const TEST_URL = 'https://torus-stage-halkbankmacedonia.asseco-see.com.tr/fim/est3Dgate';

    public const LIVE_API = 'https://epay.halkbank.mk/fim/api';

    public const TEST_API = 'https://torus-stage-halkbankmacedonia.asseco-see.com.tr/fim/api';

    /** ISO 4217 numeric codes */
    public const CURRENCIES = ['MKD' => '807', 'EUR' => '978', 'USD' => '840'];

    public static function settings(?Agency $agency): array
    {
        return $agency ? $agency->paySettings() : [];
    }

    /** Online payment is switched on and the bank data is entered */
    public static function enabled(?Agency $agency): bool
    {
        $s = self::settings($agency);

        return $agency && $agency->payment_provider === 'nestpay' && ! empty($s['enabled'])
            && filled($s['client_id'] ?? null) && filled($s['store_key'] ?? null);
    }

    public static function testMode(?Agency $agency): bool
    {
        return ! empty(self::settings($agency)['test_mode']);
    }

    /** "|" and "\" escaped as the bank wants */
    protected static function esc(?string $v): string
    {
        return str_replace('|', '\\|', str_replace('\\', '\\\\', (string) $v));
    }

    /** Hash v3: every field sorted by name (case-insensitive natural order), values joined with "|", store key last, SHA-512, base64 */
    public static function hash(array $fields, string $storeKey): string
    {
        $keys = array_keys($fields);
        natcasesort($keys);
        $s = '';
        foreach ($keys as $k) {
            $lk = strtolower((string) $k);
            if ($lk === 'hash' || $lk === 'encoding') {
                continue;
            }
            $s .= self::esc((string) $fields[$k]).'|';
        }

        return base64_encode(pack('H*', hash('sha512', $s.self::esc($storeKey))));
    }

    /** The form that is posted (automatically) to the bank's page */
    public static function form(OnlinePayment $p, Agency $agency, string $lang = 'mk'): array
    {
        $s = self::settings($agency);
        $back = route('pay.back', $p->token);
        $fields = [
            'clientId' => trim((string) $s['client_id']),
            'amount' => number_format((float) $p->amount, 2, '.', ''),
            'oid' => $p->oid,
            'okUrl' => $back,
            'failUrl' => $back,
            'TranType' => in_array($s['tran_type'] ?? 'Auth', ['Auth', 'PreAuth'], true) ? ($s['tran_type'] ?? 'Auth') : 'Auth',
            'Instalment' => '',
            'callbackUrl' => '',
            'currency' => self::CURRENCIES[$p->currency] ?? '807',
            'rnd' => (string) microtime(),
            'storeType' => '3d_pay_hosting',
            'hashAlgorithm' => 'ver3',
            'lang' => in_array($lang, ['mk', 'en'], true) ? $lang : 'mk',
            'refreshtime' => '5',
            'BillToName' => '',
            'BillToCompany' => '',
        ];
        $fields['hash'] = self::hash($fields, (string) $s['store_key']);
        $fields['encoding'] = 'UTF-8';

        return ['url' => self::testMode($agency) ? self::TEST_URL : self::LIVE_URL, 'fields' => $fields];
    }

    /** The answer of the bank is genuine (its HASH matches the one computed with the store key) */
    public static function verify(array $post, Agency $agency): bool
    {
        $got = (string) ($post['HASH'] ?? $post['hash'] ?? '');

        return $got !== '' && hash_equals(self::hash($post, (string) (self::settings($agency)['store_key'] ?? '')), $got);
    }

    /** mdStatus 1–4 = 3D authentication done; Response "Approved" = the money is taken */
    public static function approved(array $post): bool
    {
        return in_array((string) ($post['mdStatus'] ?? ''), ['1', '2', '3', '4'], true) && ($post['Response'] ?? '') === 'Approved';
    }

    /** Order status query (the bank asks for it before switching the shop to live) */
    public static function statusQuery(OnlinePayment $p, Agency $agency): ?string
    {
        $s = self::settings($agency);
        if (empty($s['status_query']) || blank($s['username'] ?? null) || blank($s['password'] ?? null)) {
            return null;
        }
        $x = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CC5Request><Name>'.$x($s['username']).'</Name><Password>'.$x($s['password']).'</Password>'
            .'<ClientId>'.$x($s['client_id']).'</ClientId><OrderId>'.$x($p->oid).'</OrderId><Mode>P</Mode><Extra><ORDERSTATUS>QUERY</ORDERSTATUS></Extra></CC5Request>';
        try {
            $res = Http::asForm()->timeout(30)->post(self::testMode($agency) ? self::TEST_API : self::LIVE_API, ['DATA' => $xml]);

            return mb_substr((string) $res->body(), 0, 4000);
        } catch (Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }
}
