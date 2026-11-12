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
    /** Max characters of agency knowledge in one prompt */
    protected const KNOWLEDGE_BUDGET = 24000;

    public static function system(int $agencyId, string $locale, string $candidates, string $query = '', ?string $viewing = null): string
    {
        $agency = Agency::find($agencyId);
        $c = ChatConfig::get($agencyId);
        $knowledge = static::knowledge($agencyId, $query);
        $lang = $locale === 'en' ? 'English' : 'Macedonian (Cyrillic)';
        $name = $c['bot_name'] ?: 'assistant';
        $contact = implode(', ', array_filter([$agency?->phone ? 'phone '.$agency->phone : null, $agency?->email ? 'e-mail '.$agency->email : null, $agency?->address ? 'address '.$agency->address.', '.$agency->city : null]));

        return "You are {$name}, the live-chat travel expert of the travel agency \"{$agency?->name}\" ({$agency?->website}). Today is ".now()->format('d.m.Y').".\n"
            ."Answer in {$lang}. Plain text, max 110 words, short lines. Sound like a real, warm person who loves travelling: vary your wording, never repeat the same opening twice, no robotic lists.\n\n"
            ."THERE ARE TWO KINDS OF CONTENT – keep them apart:\n"
            ."A) FACTS ABOUT THE AGENCY AND ITS OFFERS (prices, dates, departures, hotels, board, seats, what is included, payment, documents, visas, contacts): "
            ."take them ONLY from OFFERS and AGENCY KNOWLEDGE below, word for word for numbers and dates. Every price or date you write must appear in the OFFERS line of that exact offer. "
            ."If a fact is not there, do not guess: say an agent will confirm it (needs_human true).\n"
            ."B) GENERAL TRAVEL TALK (greetings, small talk, what to see and eat, beaches, the vibe of a place, best season, packing, travel with kids, fun facts): "
            ."here be inventive and lively – give 1–2 concrete, vivid tips or a surprising fact, use 1–2 fitting emoji, and end with ONE curious question that helps you find the right trip. "
            ."Never present general knowledge as a promise of the agency.\n\n"
            ."RULES:\n"
            ."- EVERY answer recommends 1–3 offers in \"recommend\" (keys from OFFERS, e.g. \"tour:12\"), the best fit first – also for small talk (pick what fits the season or the conversation). "
            ."Name them in the reply in a natural sentence and say in a few words why they fit. The website shows each recommended offer as a card with a link, so never write URLs and never list offers that are not in \"recommend\".\n"
            ."- Use ONLY offers from OFFERS. Never invent offers, prices, dates, hotels or availability. If nothing fits well, recommend the closest ones, say so honestly and offer a personal offer from an agent (needs_human true).\n"
            ."- Earlier answers show the offers they presented as [shown: …]. When the visitor says \"that one\", \"the second\", \"how much is it\", they mean those offers.\n"
            ."- Prices and seats can change: say \"from\" and invite them to book or ask an agent.\n"
            ."- If the visitor wants a person, wants to book something complex, complains, or you are unsure: set needs_human true and ask for phone or e-mail.\n"
            ."- Never ask for passport numbers, card numbers or other sensitive data.\n"
            ."- If the question has nothing to do with travel, answer in one friendly, witty sentence and steer back to travel.\n"
            .($contact ? "- Agency contact: {$contact}. Working hours: ".($c['hours'][$locale] ?? $c['hours']['mk'] ?? '')."\n" : '')
            .(filled($c['instructions']) ? "\nINSTRUCTIONS FROM THE AGENCY (follow them):\n".Str::limit($c['instructions'], 3000)."\n" : '')
            .($knowledge ? "\nAGENCY KNOWLEDGE (trust this over anything else; \"Q/A\" entries are answers corrected by the agency – answer such questions the same way):\n{$knowledge}\n" : '')
            ."\n".ChatCatalog::overview($agencyId)."\n"
            .($viewing ? "The visitor is now on the page of this offer: {$viewing}\n" : '')
            ."OFFERS (key | details):\n{$candidates}\n\n"
            .'Answer ONLY with JSON: {"reply": "<text for the visitor>", "recommend": ["<key>", …1 to 3], "needs_human": false, "contact": {"name": null, "phone": null, "email": null}}'
            .' – fill "contact" only with data the visitor wrote.';
    }

    /** The knowledge entries that matter for this question: corrections and matching entries first, within a size budget */
    protected static function knowledge(int $agencyId, string $query): string
    {
        $q = ChatCatalog::words($query);
        $rank = ['correction' => 30, 'manual' => 20, 'analysis' => 0];
        $entries = ChatKnowledge::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->latest('id')->limit(400)->get()
            ->map(function (ChatKnowledge $k) use ($q, $rank) {
                $hits = $q ? count(array_intersect($q, ChatCatalog::words($k->title.' '.$k->content))) : 0;
                $titleHits = $q ? count(array_intersect($q, ChatCatalog::words($k->title))) : 0;
                $k->setAttribute('score', $hits * 10 + $titleHits * 15 + ($rank[$k->source] ?? 0));

                return $k;
            })
            ->sortByDesc('score');

        $out = [];
        $size = 0;
        foreach ($entries as $k) {
            $text = $k->source === 'correction'
                ? "Q: {$k->title}\nA: ".Str::limit($k->content, 1500)
                : "### {$k->title}\n".Str::limit($k->content, 1500);
            if ($size + mb_strlen($text) > static::KNOWLEDGE_BUDGET) {
                continue;
            }
            $out[] = $text;
            $size += mb_strlen($text);
        }

        return implode("\n\n", $out);
    }

    /** Candidate offers: the page the visitor is on and offers shown earlier always stay in the list */
    protected static function candidates(int $agencyId, string $query, ?array $types = null, int $limit = 40, array $pinned = []): string
    {
        $lines = [];
        foreach (array_unique($pinned) as $key) {
            if ($line = ChatCatalog::line($agencyId, $key)) {
                $lines[$key] = $line;
            }
        }
        foreach (ChatCatalog::relevant($agencyId, $query, $limit, $types) as $key => $i) {
            $lines[$key] ??= "{$key} | {$i['name']} | {$i['line']}";
        }

        return implode("\n", $lines) ?: '(no active offers)';
    }

    /** Keys ("tour:12") of the cards attached to a message */
    protected static function keysOf(ChatMessage $m): array
    {
        return collect($m->items ?? [])->map(fn ($i) => ($i['type'] ?? '').':'.($i['id'] ?? ''))->filter(fn ($k) => ! str_starts_with($k, ':') && ! str_ends_with($k, ':'))->values()->all();
    }

    /** Reply to the visitor's last message. Returns the bot message (or null when the bot is paused). */
    public static function reply(ChatConversation $conv): ?ChatMessage
    {
        if ($conv->bot_paused) {
            return null;
        }
        $agencyId = (int) $conv->agency_id;
        $history = $conv->messages()->latest('id')->limit(16)->get()->reverse()->values();
        $query = $history->where('role', 'visitor')->take(-3)->pluck('body')->implode(' ');

        $viewing = ChatCatalog::fromUrl($agencyId, $conv->page_url);
        $shown = [];
        $messages = [];
        foreach ($history as $m) {
            if ($m->role === 'system') {
                continue;
            }
            $role = $m->role === 'visitor' ? 'user' : 'assistant';
            $body = $m->role === 'operator' ? '[agent] '.$m->body : $m->body;
            if ($m->role !== 'visitor' && ($keys = static::keysOf($m))) {
                $shown = array_merge($shown, $keys);
                $body .= "\n[shown: ".collect($keys)->map(fn ($k) => $k.' '.(ChatCatalog::items($agencyId)[$k]['name'] ?? ''))->implode('; ').']';
            }
            if ($messages && end($messages)['role'] === $role) {
                $messages[count($messages) - 1]['content'] .= "\n".$body;
            } else {
                $messages[] = ['role' => $role, 'content' => $body];
            }
        }
        if (! $messages || $messages[0]['role'] !== 'user') {
            array_unshift($messages, ['role' => 'user', 'content' => '(the visitor opened the chat)']);
        }
        $pinned = array_merge($viewing ? [$viewing] : [], array_reverse(array_slice($shown, -8)));

        try {
            $system = static::system($agencyId, $conv->locale, static::candidates($agencyId, $query, null, 40, $pinned), $query, $viewing ? ChatCatalog::line($agencyId, $viewing) : null);
            $out = Ai::json($agencyId, 'chat', $system, $messages, 900, null, 'chat #'.$conv->id);
            $conv->cost = (float) $conv->cost + Ai::$lastCost;
        } catch (AiException $e) {
            Log::info('Chatbot unavailable: '.$e->getMessage());

            return static::handoff($conv);
        } catch (Throwable $e) {
            Log::error('Chatbot error: '.$e->getMessage());

            return static::handoff($conv);
        }

        return static::store($conv, $out, trim($query.' '.($viewing ?? '')), $viewing);
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
        $query = $summary.' '.$q->label('title', 'mk').' '.$q->category;

        try {
            $prompt = "The visitor filled the questionnaire \"{$q->label('title', 'mk')}\" (category: {$q->category}). Answers:\n{$summary}\n\n"
                .'Recommend the 2–3 best matching offers from the OFFERS list and explain in 1 sentence each why they fit. If nothing fits well, recommend the closest ones, say so and offer a personal offer from an agent (needs_human true).';
            $out = Ai::json($agencyId, 'chat', static::system($agencyId, $conv->locale, static::candidates($agencyId, $query, $types, 60), $query), [['role' => 'user', 'content' => $prompt]], 1000, null, 'questionnaire #'.$q->id);
            $conv->cost = (float) $conv->cost + Ai::$lastCost;

            return static::store($conv, $out, $query);
        } catch (Throwable $e) {
            // without AI: best keyword matches
            $items = array_keys(ChatCatalog::relevant($agencyId, $query, 3, $types));
            $text = $conv->locale === 'en' ? 'Based on your answers, these offers could suit you. An agent can prepare a personal offer too.' : 'Според вашите одговори, овие понуди би ви одговарале. Наш агент може да ви подготви и лична понуда.';

            return static::store($conv, ['reply' => $text, 'recommend' => $items, 'needs_human' => true], $query);
        }
    }

    protected static function store(ChatConversation $conv, array $out, string $query = '', ?string $viewing = null): ChatMessage
    {
        $agencyId = (int) $conv->agency_id;
        $items = [];
        $keys = [];
        foreach (array_slice((array) ($out['recommend'] ?? []), 0, 3) as $key) {
            if (is_string($key) && ! in_array($key = trim($key), $keys, true) && ($card = ChatCatalog::card($agencyId, $key))) {
                $items[] = $card;   // unknown or inactive keys are dropped
                $keys[] = $key;
            }
        }
        // every answer carries at least one link to an offer
        if (! $items) {
            $fallback = $viewing && ChatCatalog::card($agencyId, $viewing) ? [$viewing] : ChatCatalog::best($agencyId, $query, 2);
            foreach ($fallback as $key) {
                if ($card = ChatCatalog::card($agencyId, $key)) {
                    $items[] = $card;
                }
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
