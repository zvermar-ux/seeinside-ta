<?php

namespace App\Services\Ai;

use App\Models\Agency;
use App\Models\ChatConversation;
use App\Models\ChatKnowledge;
use App\Models\ChatMessage;
use App\Models\ChatQuestionnaire;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** The website chatbot: answers from the agency knowledge and recommends only active offers */
class ChatBot
{
    public static function system(int $agencyId, string $locale, string $candidates): string
    {
        $agency = Agency::find($agencyId);
        $c = ChatConfig::get($agencyId);
        $knowledge = ChatKnowledge::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->orderByRaw("case source when 'correction' then 0 when 'manual' then 1 else 2 end")->latest('id')->limit(80)->get()
            ->map(fn ($k) => "### {$k->title}\n".Str::limit($k->content, 1500))->implode("\n\n");
        $lang = $locale === 'en' ? 'English' : 'Macedonian (Cyrillic)';
        $name = $c['bot_name'] ?: 'assistant';
        $contact = implode(', ', array_filter([$agency?->phone ? 'phone '.$agency->phone : null, $agency?->email ? 'e-mail '.$agency->email : null, $agency?->address ? 'address '.$agency->address.', '.$agency->city : null]));

        return "You are {$name}, the friendly live-chat assistant of the travel agency \"{$agency?->name}\" ({$agency?->website}). Today is ".now()->format('d.m.Y').".\n"
            ."Answer in {$lang}, short (max 90 words), warm and helpful. Use plain text, at most a few short lines.\n"
            ."RULES:\n"
            ."- Recommend ONLY offers from the OFFERS list below, by their key (e.g. \"tour:12\"). Never invent offers, prices, dates, hotels or availability.\n"
            ."- If nothing in the list fits, say so honestly and offer that an agent will prepare an offer (set needs_human true).\n"
            ."- Prices and seats can change: say \"from\" and invite them to book or ask an agent.\n"
            ."- If the visitor wants a person, wants to book something complex, complains, or you are unsure: set needs_human true and ask for phone or e-mail.\n"
            ."- Never ask for passport numbers, card numbers or other sensitive data.\n"
            ."- Do not talk about topics unrelated to travel and the agency; politely steer back.\n"
            .($contact ? "- Agency contact: {$contact}. Working hours: ".($c['hours'][$locale] ?? $c['hours']['mk'] ?? '')."\n" : '')
            .(filled($c['instructions']) ? "\nINSTRUCTIONS FROM THE AGENCY:\n".Str::limit($c['instructions'], 3000)."\n" : '')
            .($knowledge ? "\nAGENCY KNOWLEDGE (trust this, corrections first):\n{$knowledge}\n" : '')
            ."\n".ChatCatalog::overview($agencyId)."\nOFFERS (key | details):\n{$candidates}\n\n"
            .'Answer ONLY with JSON: {"reply": "<text for the visitor>", "recommend": ["<key>", …max 4], "needs_human": false, "contact": {"name": null, "phone": null, "email": null}}'
            .' – fill "contact" only with data the visitor wrote.';
    }

    protected static function candidates(int $agencyId, string $query, ?array $types = null, int $limit = 40): string
    {
        return collect(ChatCatalog::relevant($agencyId, $query, $limit, $types))
            ->map(fn ($i, $key) => "{$key} | {$i['name']} | {$i['line']}")->implode("\n") ?: '(no active offers)';
    }

    /** Reply to the visitor's last message. Returns the bot message (or null when the bot is paused). */
    public static function reply(ChatConversation $conv): ?ChatMessage
    {
        if ($conv->bot_paused) {
            return null;
        }
        $agencyId = (int) $conv->agency_id;
        $history = $conv->messages()->latest('id')->limit(14)->get()->reverse()->values();
        $query = $history->where('role', 'visitor')->take(-3)->pluck('body')->implode(' ');

        $messages = [];
        foreach ($history as $m) {
            if ($m->role === 'system') {
                continue;
            }
            $role = $m->role === 'visitor' ? 'user' : 'assistant';
            $body = $m->role === 'operator' ? '[agent] '.$m->body : $m->body;
            if ($messages && end($messages)['role'] === $role) {
                $messages[count($messages) - 1]['content'] .= "\n".$body;
            } else {
                $messages[] = ['role' => $role, 'content' => $body];
            }
        }
        if (! $messages || $messages[0]['role'] !== 'user') {
            array_unshift($messages, ['role' => 'user', 'content' => '(the visitor opened the chat)']);
        }

        try {
            $out = Ai::json($agencyId, 'chat', static::system($agencyId, $conv->locale, static::candidates($agencyId, $query)), $messages, 900, null, 'chat #'.$conv->id);
            $conv->cost = (float) $conv->cost + Ai::$lastCost;
        } catch (AiException $e) {
            Log::info('Chatbot unavailable: '.$e->getMessage());

            return static::handoff($conv);
        } catch (Throwable $e) {
            Log::error('Chatbot error: '.$e->getMessage());

            return static::handoff($conv);
        }

        return static::store($conv, $out);
    }

    /** Recommendations from a questionnaire */
    public static function recommend(ChatConversation $conv, ChatQuestionnaire $q, array $answers): ChatMessage
    {
        $agencyId = (int) $conv->agency_id;
        $lines = [];
        foreach ($q->questions ?? [] as $x) {
            $a = $answers[$x['key'] ?? ''] ?? null;
            if ($a !== null && $a !== '' && $a !== []) {
                $lines[] = ($x['label']['mk'] ?? $x['label']['en'] ?? $x['key']).': '.(is_array($a) ? implode(', ', $a) : $a);
            }
        }
        $summary = implode("\n", $lines);
        $types = $q->offer_types ?: null;
        $conv->forceFill(['questionnaire_id' => $q->id, 'answers' => $answers])->save();
        $conv->messages()->create(['role' => 'visitor', 'body' => "📝 {$q->label('title', $conv->locale)}\n{$summary}"]);

        try {
            $query = $summary.' '.$q->label('title', 'mk').' '.$q->category;
            $prompt = "The visitor filled the questionnaire \"{$q->label('title', 'mk')}\" (category: {$q->category}). Answers:\n{$summary}\n\n"
                .'Recommend the 2–4 best matching offers from the OFFERS list and explain in 1 sentence each why they fit. If nothing fits well, say so and offer a personal offer from an agent (needs_human true).';
            $out = Ai::json($agencyId, 'chat', static::system($agencyId, $conv->locale, static::candidates($agencyId, $query, $types, 60)), [['role' => 'user', 'content' => $prompt]], 1000, null, 'questionnaire #'.$q->id);
            $conv->cost = (float) $conv->cost + Ai::$lastCost;

            return static::store($conv, $out);
        } catch (Throwable $e) {
            // without AI: best keyword matches
            $items = array_keys(ChatCatalog::relevant($agencyId, $summary.' '.$q->category, 3, $types));
            $text = $conv->locale === 'en' ? 'Based on your answers, these offers could suit you. An agent can prepare a personal offer too.' : 'Според вашите одговори, овие понуди би ви одговарале. Наш агент може да ви подготви и лична понуда.';

            return static::store($conv, ['reply' => $text, 'recommend' => $items, 'needs_human' => true]);
        }
    }

    protected static function store(ChatConversation $conv, array $out): ChatMessage
    {
        $agencyId = (int) $conv->agency_id;
        $items = [];
        foreach (array_slice((array) ($out['recommend'] ?? []), 0, 4) as $key) {
            if (is_string($key) && ($card = ChatCatalog::card($agencyId, trim($key)))) {
                $items[] = $card;   // unknown or inactive keys are dropped
            }
        }
        $reply = trim((string) ($out['reply'] ?? ''));
        if ($reply === '') {
            $reply = $conv->locale === 'en' ? 'Sorry, could you say that again?' : 'Извинете, може ли повторно?';
        }
        $contact = (array) ($out['contact'] ?? []);
        foreach (['name', 'phone', 'email'] as $f) {
            $v = trim((string) ($contact[$f] ?? ''));
            if ($v !== '' && blank($conv->{$f}) && ($f !== 'email' || filter_var($v, FILTER_VALIDATE_EMAIL))) {
                $conv->{$f} = Str::limit(strip_tags($v), 120, '');
            }
        }
        if (! empty($out['needs_human'])) {
            $conv->needs_human = true;
        }
        $msg = $conv->messages()->create(['role' => 'bot', 'body' => Str::limit(strip_tags($reply), 2000), 'items' => $items ?: null]);
        $conv->forceFill(['last_message_at' => now(), 'messages_count' => $conv->messages_count + 1])->save();

        return $msg;
    }

    public static function handoff(ChatConversation $conv): ChatMessage
    {
        $c = ChatConfig::get((int) $conv->agency_id);
        $text = $c['handoff'][$conv->locale] ?? $c['handoff']['mk'] ?? '';
        $conv->forceFill(['needs_human' => true])->save();

        return $conv->messages()->create(['role' => 'bot', 'body' => $text]);
    }
}
