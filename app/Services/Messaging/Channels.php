<?php

namespace App\Services\Messaging;

use App\Models\Agency;
use Illuminate\Support\Facades\Http;

/**
 * Sending a text over WhatsApp / Viber with the provider the agency configured:
 *  - WhatsApp: Meta WhatsApp Cloud API (own Business number) or Infobip
 *  - Viber: Infobip (Viber Business Messages)
 * Returns [ok, provider reference or error].
 */
class Channels
{
    public static function providers(): array
    {
        return [
            'whatsapp' => ['' => __('Off'), 'meta' => 'Meta WhatsApp Cloud API', 'infobip' => 'Infobip'],
            'viber' => ['' => __('Off'), 'infobip' => 'Infobip (Viber Business Messages)'],
        ];
    }

    public static function enabled(Agency $a, string $channel): bool
    {
        if ($channel === 'email') {
            return $a->msg('email.enabled', true) !== false;
        }
        if (! $a->hasModule('messaging')) {
            return false;
        }

        return filled($a->msg($channel.'.provider')) && $a->msg($channel.'.enabled', true) !== false;
    }

    /** +389 70 123 456 / 070123456 → 38970123456 (international, digits only) */
    public static function phone(?string $raw, ?Agency $a = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($raw, '+') || str_starts_with($d, '00')) {
            $d = ltrim($d, '0');
        } elseif (str_starts_with($d, '0')) {
            $d = ($a?->msg('country_code', '389') ?: '389').substr($d, 1);
        }

        return strlen($d) >= 8 ? $d : null;
    }

    public static function send(Agency $a, string $channel, string $phone, string $text): array
    {
        $provider = (string) $a->msg($channel.'.provider');
        try {
            return match ($channel.':'.$provider) {
                'whatsapp:meta' => self::metaWhatsApp($a, $phone, $text),
                'whatsapp:infobip' => self::infobipWhatsApp($a, $phone, $text),
                'viber:infobip' => self::infobipViber($a, $phone, $text),
                default => [false, __('The channel is not set up.')],
            };
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    protected static function metaWhatsApp(Agency $a, string $to, string $text): array
    {
        $id = $a->msg('whatsapp.phone_number_id');
        $token = $a->msg('whatsapp.token');
        $template = $a->msg('whatsapp.template');
        // outside the 24 h window WhatsApp only delivers approved templates: one template with a single {{1}} body variable
        $body = filled($template)
            ? ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => [
                'name' => $template, 'language' => ['code' => $a->msg('whatsapp.template_lang', 'mk')],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => self::oneLine($text)]]]],
            ]]
            : ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => true, 'body' => $text]];
        $r = Http::withToken((string) $token)->timeout(20)->post('https://graph.facebook.com/v21.0/'.$id.'/messages', $body);

        return $r->successful() ? [true, (string) $r->json('messages.0.id')] : [false, (string) ($r->json('error.message') ?: 'HTTP '.$r->status())];
    }

    protected static function infobip(Agency $a, string $channel): \Illuminate\Http\Client\PendingRequest
    {
        $base = rtrim((string) $a->msg($channel.'.base_url'), '/');
        if (! str_starts_with($base, 'https://')) {
            $base = 'https://'.ltrim($base, '/');
        }

        return Http::baseUrl($base)->withHeaders(['Authorization' => 'App '.$a->msg($channel.'.api_key')])->acceptJson()->timeout(20);
    }

    protected static function infobipWhatsApp(Agency $a, string $to, string $text): array
    {
        $r = self::infobip($a, 'whatsapp')->post('/whatsapp/1/message/text', ['from' => preg_replace('/\D+/', '', (string) $a->msg('whatsapp.sender')), 'to' => $to, 'content' => ['text' => $text]]);

        return $r->successful() ? [true, (string) $r->json('messageId')] : [false, (string) ($r->json('requestError.serviceException.text') ?: 'HTTP '.$r->status())];
    }

    protected static function infobipViber(Agency $a, string $to, string $text): array
    {
        $r = self::infobip($a, 'viber')->post('/viber/2/messages', ['messages' => [[
            'sender' => (string) $a->msg('viber.sender'), 'destinations' => [['to' => $to]], 'content' => ['text' => $text, 'type' => 'TEXT'],
        ]]]);

        return $r->successful() ? [true, (string) $r->json('messages.0.messageId')] : [false, (string) ($r->json('requestError.serviceException.text') ?: 'HTTP '.$r->status())];
    }

    protected static function oneLine(string $t): string
    {
        // template variables may not contain new lines or more than 4 spaces
        return trim(preg_replace('/\s{2,}|\R+/u', ' · ', $t));
    }

    /** Links that open WhatsApp / Viber with the text ready (no provider needed) */
    public static function clickLinks(?string $phone, string $text, ?Agency $a = null): array
    {
        $p = self::phone($phone, $a);
        if (! $p) {
            return [];
        }

        return [
            'whatsapp' => 'https://wa.me/'.$p.'?text='.rawurlencode($text),
            'viber' => 'viber://chat?number=%2B'.$p.'&draft='.rawurlencode($text),
        ];
    }
}
