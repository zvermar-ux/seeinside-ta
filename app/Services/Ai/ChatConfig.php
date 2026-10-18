<?php

namespace App\Services\Ai;

use App\Models\AiAccount;
use App\Models\ChatQuestionnaire;
use App\Services\Website\CatalogPresenter as P;

/** Live chat settings: defaults, and the public part sent to the website */
class ChatConfig
{
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'position' => 'right',
            'color' => '#0e7490',
            'title' => ['mk' => 'Тука сме за вас', 'en' => 'We are here for you'],
            'subtitle' => ['mk' => 'Обично одговараме за неколку минути', 'en' => 'We usually reply within minutes'],
            'greeting' => ['mk' => 'Здраво! 👋 Како сакате да разговараме?', 'en' => 'Hi! 👋 How would you like to talk?'],
            'launcher' => ['mk' => 'Разговор', 'en' => 'Chat'],
            'bot_name' => 'Travel асистент',
            'avatar' => null,
            'viber' => null,          // viber://pa?chatURI=… or phone number
            'messenger' => null,      // m.me/… or page username
            'whatsapp' => null,
            'phone' => null,
            'email' => null,
            'show_ai' => true,
            'show_questionnaires' => true,
            'ask_contact' => true,
            'hours' => ['mk' => 'Пон–Пет 09–17 ч.', 'en' => 'Mon–Fri 9am–5pm'],
            'instructions' => '',
            'handoff' => ['mk' => 'Ќе ве поврзам со наш агент. Оставете телефон или e-mail и ќе ви се јавиме.', 'en' => 'I will connect you with an agent. Leave your phone or e-mail and we will contact you.'],
            'privacy' => ['mk' => 'Разговорот го чуваме за да ви одговориме. Не внесувајте лични документи.', 'en' => 'We keep the conversation to answer you. Please do not share personal documents.'],
        ];
    }

    public static function get(?int $agencyId): array
    {
        $saved = AiAccount::for($agencyId)->chat ?? [];

        return array_replace(static::defaults(), is_array($saved) ? $saved : []);
    }

    /** What the website needs to draw the widget (no keys, no instructions) */
    public static function forWebsite(int $agencyId): array
    {
        $c = static::get($agencyId);
        $ai = (bool) $c['show_ai'] && Ai::available($agencyId, 'chat');
        $questionnaires = $c['show_questionnaires']
            ? ChatQuestionnaire::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ChatQuestionnaire $q) => [
                    'id' => $q->id, 'title' => $q->title, 'intro' => $q->intro, 'icon' => $q->icon, 'image' => P::url($q->image),
                    'category' => $q->category,
                    'questions' => collect($q->questions ?? [])->map(fn ($x) => [
                        'key' => $x['key'] ?? null, 'label' => $x['label'] ?? [], 'type' => $x['type'] ?? 'single',
                        'options' => collect($x['options'] ?? [])->map(fn ($o) => is_array($o) ? $o : ['mk' => $o, 'en' => null])->values()->all(),
                    ])->values()->all(),
                ])->values()->all()
            : [];

        return [
            'enabled' => (bool) $c['enabled'],
            'position' => $c['position'], 'color' => $c['color'],
            'title' => $c['title'], 'subtitle' => $c['subtitle'], 'greeting' => $c['greeting'], 'launcher' => $c['launcher'],
            'bot_name' => $c['bot_name'], 'avatar' => P::url($c['avatar']),
            'channels' => array_values(array_filter([
                $c['viber'] ? ['type' => 'viber', 'url' => static::viberUrl($c['viber'])] : null,
                $c['messenger'] ? ['type' => 'messenger', 'url' => static::messengerUrl($c['messenger'])] : null,
                $c['whatsapp'] ? ['type' => 'whatsapp', 'url' => 'https://wa.me/'.preg_replace('/\D+/', '', $c['whatsapp'])] : null,
                $c['phone'] ? ['type' => 'phone', 'url' => 'tel:'.preg_replace('/[^\d+]/', '', $c['phone']), 'label' => $c['phone']] : null,
                $c['email'] ? ['type' => 'email', 'url' => 'mailto:'.$c['email'], 'label' => $c['email']] : null,
            ])),
            'ai' => $ai,
            'ask_contact' => (bool) $c['ask_contact'],
            'hours' => $c['hours'], 'privacy' => $c['privacy'],
            'questionnaires' => $questionnaires,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public static function viberUrl(string $v): string
    {
        $v = trim($v);
        if (str_starts_with($v, 'viber://') || str_starts_with($v, 'http')) {
            return $v;
        }
        $digits = preg_replace('/[^\d]/', '', $v);

        return $digits && strlen($digits) >= 8 ? 'viber://chat?number=%2B'.$digits : 'viber://pa?chatURI='.rawurlencode($v);
    }

    public static function messengerUrl(string $v): string
    {
        $v = trim($v);
        if (str_starts_with($v, 'http')) {
            return $v;
        }

        return 'https://m.me/'.ltrim(preg_replace('#^(m\.me/|facebook\.com/)#', '', $v), '/@');
    }
}
