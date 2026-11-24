<?php

namespace App\Services\Ai;

use App\Models\Agency;
use App\Models\ApiClient;
use App\Models\ChatKnowledge;
use App\Models\ChatQuestionnaire;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** Teaching the chatbot: analysis of the website and the offer, questionnaires made by AI */
class ChatTrainer
{
    /** Text of the agency website: home page + pages like contact, about, terms, payment, FAQ */
    public static function websiteText(int $agencyId, int $max = 8, int $chars = 6000): array
    {
        $agency = Agency::find($agencyId);
        $base = ApiClient::withoutGlobalScopes()->where('agency_id', $agencyId)->whereNotNull('site_url')->value('site_url') ?: $agency?->website;
        if (! $base) {
            return [];
        }
        $base = rtrim($base, '/');
        if (! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.ltrim($base, '/');
        }
        $host = parse_url($base, PHP_URL_HOST);
        $pages = [];
        $get = function (string $url) {
            try {
                $r = Http::timeout(15)->withHeaders(['User-Agent' => 'SeeInside-Chatbot-Trainer/1.0'])->get($url);

                return $r->successful() ? (string) $r->body() : null;
            } catch (Throwable) {
                return null;
            }
        };
        $home = $get($base.'/');
        if (! $home) {
            return [];
        }
        $pages[$base.'/'] = static::clean($home, $chars);
        preg_match_all('/href=["\']([^"\'#?]+)["\']/i', $home, $m);
        $want = '/(kontakt|contact|za-nas|about|uslovi|terms|plakjanje|placanje|payment|faq|prasanja|cesto|osiguruvanje|insurance|vizi|visa|politika|privacy|dokument|otkaz|rati|popust|early|last-minute)/i';
        $skip = '/\.(jpe?g|png|webp|gif|svg|pdf|zip|css|js|xml)$|wp-(content|admin|json|login)|\/feed\/|\/(tag|author|page)\//i';
        $all = collect($m[1])->map(fn ($u) => str_starts_with($u, '/') ? 'https://'.$host.$u : $u)
            ->filter(fn ($u) => parse_url($u, PHP_URL_HOST) === $host && ! preg_match($skip, $u) && rtrim($u, '/') !== $base)->unique();
        // pages about the agency and its rules first, then the other pages of the menu
        $links = $all->filter(fn ($u) => preg_match($want, rawurldecode($u)))->merge($all->reject(fn ($u) => preg_match($want, rawurldecode($u))))->unique()->take($max);
        $terms = $agency?->terms_url;
        if ($terms && parse_url($terms, PHP_URL_HOST) === $host) {
            $links->prepend($terms);
        }
        foreach ($links->unique()->take($max) as $u) {
            if ($html = $get($u)) {
                $pages[$u] = static::clean($html, $chars);
            }
        }

        return $pages;
    }

    protected static function clean(string $html, int $chars = 6000): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|nav|footer|header)[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|p|div|li|h[1-6])[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/[ \t]+/', ' ', $text))), $chars);
    }

    /**
     * Questions & answers for the bot: the questions visitors really ask (booking, paying, documents, destinations,
     * transport, children…) answered only from the website, the offer and the knowledge. Replaces the earlier Q&A.
     */
    public static function faq(int $agencyId): int
    {
        $agency = Agency::findOrFail($agencyId);
        $pages = static::websiteText($agencyId, 20, 4000);
        $groups = Group::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->get()
            ->map(fn ($g) => $g->getTranslation('name', 'mk', false) ?: $g->display_name)->filter()->implode(', ');
        $items = collect(ChatCatalog::items($agencyId))->take(200)->map(fn ($i, $k) => "{$k} | {$i['name']} | {$i['line']}")->implode("\n");
        $known = ChatKnowledge::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->where('source', '!=', 'faq')
            ->latest('id')->limit(60)->get()->map(fn ($k) => "### {$k->title}\n".Str::limit($k->content, 1200))->implode("\n\n");
        $data = "AGENCY: {$agency->name}, {$agency->address}, {$agency->city}, phone {$agency->phone}, e-mail {$agency->email}, web {$agency->website}"
            .($agency->terms_url ? ", general terms of travel: {$agency->terms_url}" : '')."\n"
            ."DESTINATIONS / GROUPS: {$groups}\n\nKNOWLEDGE ALREADY ENTERED:\n{$known}\n\nACTIVE OFFER (key | name | details):\n{$items}\n\n"
            .collect($pages)->map(fn ($t, $u) => "WEBSITE PAGE {$u}:\n{$t}")->implode("\n\n");
        $data = Str::limit($data, 90000);

        $topics = [
            'the agency, contacts and working hours, how to book (online, phone, office), payment (advance, rest, cards, instalments), cancellation and changes, general terms of travel, travel insurance, documents, passports and visas, children and discounts',
            'summer holidays and stays: which countries and places, apartments and hotels, fixed terms and nights, what the price includes (board, tourist tax), rooms and capacity, early booking / last minute, beaches and distances, transport to the destination (bus, own car, flight), departure places and times',
            'organised tours, city breaks, excursions, events and concerts, winter / ski, spa, group travel, what is included in a tour, guides, seats in the bus, best offers for families, couples and seniors, what to recommend for a budget or a month',
        ];
        $all = collect();
        foreach ($topics as $n => $topic) {
            $prompt = "You build the knowledge base of the website chatbot of a travel agency. Topic of this part: {$topic}.\n"
                ."Write 15–25 QUESTION & ANSWER pairs in Macedonian (Cyrillic). Questions as visitors really write them (short, everyday words). "
                ."Answers: 1–5 sentences, concrete facts ONLY from the data below (names of destinations and offers, prices \"од\", dates, contacts, rules). "
                ."Never invent prices, dates, rules or services. If the data does not answer an important question, answer that an agent will give the details and give the contact. "
                ."Do not repeat the same question twice.\n"
                .'Return {"qa": [{"q": "", "a": ""}]}'
                ."\n\nDATA:\n{$data}";
            try {
                $out = Ai::json($agencyId, 'training', 'You are an expert in travel agency customer service. You write precise, friendly FAQ answers.', [['role' => 'user', 'content' => $prompt]], 8000, Auth::id(), 'Q&A '.($n + 1).'/3');
            } catch (AiException $e) {
                if ($all->isEmpty() && $n === count($topics) - 1) {
                    throw $e;
                }

                continue;
            }
            $all = $all->merge(collect($out['qa'] ?? [])->filter(fn ($e) => filled($e['q'] ?? null) && filled($e['a'] ?? null)));
        }
        $all = $all->unique(fn ($e) => mb_strtolower(trim($e['q'])));
        if ($all->isEmpty()) {
            throw new AiException(__('The analysis returned nothing. Please try again.'));
        }
        ChatKnowledge::withoutGlobalScopes()->where('agency_id', $agencyId)->where('source', 'faq')->delete();
        foreach ($all as $e) {
            ChatKnowledge::create(['agency_id' => $agencyId, 'title' => Str::limit(trim($e['q']), 190), 'content' => trim($e['a']), 'source' => 'faq', 'created_by' => Auth::id()]);
        }

        return $all->count();
    }

    /** Builds (replaces) the "analysis" knowledge entries. Returns the number of entries. */
    public static function analyze(int $agencyId): int
    {
        $agency = Agency::findOrFail($agencyId);
        $pages = static::websiteText($agencyId);
        $groups = Group::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->get()
            ->map(fn ($g) => $g->getTranslation('name', 'mk', false) ?: $g->display_name)->filter()->implode(', ');
        $items = collect(ChatCatalog::items($agencyId))->take(150)->map(fn ($i, $k) => "{$k} | {$i['name']} | {$i['line']}")->implode("\n");

        $prompt = "Analyse this travel agency (website text, destinations and the active offer) and write a knowledge base for its website chatbot.\n"
            ."Write 6–14 entries in Macedonian. Each entry: a clear title and 2–8 sentences of FACTS only (what the agency offers, main destinations and seasons, typical prices \"from\", "
            ."how to book and pay, documents/visas, departure places, contacts and working hours, special strengths). Do not invent anything that is not in the data.\n"
            .'Return {"entries": [{"title": "", "content": ""}]}'
            ."\n\nAGENCY: {$agency->name}, {$agency->city}, phone {$agency->phone}, e-mail {$agency->email}, web {$agency->website}\n"
            ."DESTINATIONS / GROUPS: {$groups}\n\nACTIVE OFFER:\n{$items}\n\n"
            .collect($pages)->map(fn ($t, $u) => "WEBSITE PAGE {$u}:\n{$t}")->implode("\n\n");
        $out = Ai::json($agencyId, 'training', 'You are an expert in travel agency customer service and knowledge management.', [['role' => 'user', 'content' => Str::limit($prompt, 60000)]], 6000, Auth::id(), 'website analysis');

        $entries = collect($out['entries'] ?? [])->filter(fn ($e) => filled($e['title'] ?? null) && filled($e['content'] ?? null));
        if ($entries->isEmpty()) {
            throw new AiException(__('The analysis returned nothing. Please try again.'));
        }
        ChatKnowledge::withoutGlobalScopes()->where('agency_id', $agencyId)->where('source', 'analysis')->delete();
        foreach ($entries as $e) {
            ChatKnowledge::create(['agency_id' => $agencyId, 'title' => Str::limit($e['title'], 190), 'content' => $e['content'], 'source' => 'analysis', 'created_by' => Auth::id()]);
        }

        return $entries->count();
    }

    /** New questionnaire from a short brief, e.g. "Зимување 2027 – семејства" */
    public static function questionnaire(int $agencyId, string $brief, ?string $category = null, int $count = 5): ChatQuestionnaire
    {
        $offer = ChatCatalog::overview($agencyId);
        $sample = collect(ChatCatalog::relevant($agencyId, $brief.' '.$category, 25))->map(fn ($i) => $i['name'].' | '.$i['line'])->implode("\n");
        $prompt = "Create a short questionnaire for the website chat of a travel agency that helps a visitor choose a trip. Topic: \"{$brief}\""
            .($category ? " (category {$category})" : '').". {$count} questions, easy to answer with one click (types: single, multi; at most one \"text\" question). "
            ."Options must help to pick between the agency's offers below (destinations, budget, dates, who travels, type of stay). Questions and options in Macedonian (mk) and English (en).\n"
            .'Return {"title": {"mk": "", "en": ""}, "intro": {"mk": "", "en": ""}, "icon": "<one emoji>", "category": "winter|summer|spa|travel|city|event|other", "offer_types": ["tour","event","accommodation"], '
            .'"questions": [{"key": "budget", "label": {"mk": "", "en": ""}, "type": "single", "options": [{"mk": "", "en": ""}]}]}'
            ."\n\n{$offer}\nEXAMPLES OF THE OFFER:\n{$sample}";
        $out = Ai::json($agencyId, 'training', 'You design short, friendly decision questionnaires for travel agencies.', [['role' => 'user', 'content' => $prompt]], 3000, Auth::id(), 'questionnaire: '.Str::limit($brief, 60));

        $questions = collect($out['questions'] ?? [])->filter(fn ($q) => filled($q['label']['mk'] ?? null))->map(fn ($q, $i) => [
            'key' => Str::slug($q['key'] ?? ('q'.($i + 1)), '_') ?: 'q'.($i + 1),
            'label' => ['mk' => $q['label']['mk'] ?? '', 'en' => $q['label']['en'] ?? null],
            'type' => in_array($q['type'] ?? '', ['single', 'multi', 'text'], true) ? $q['type'] : 'single',
            'options' => collect($q['options'] ?? [])->map(fn ($o) => ['mk' => is_array($o) ? ($o['mk'] ?? '') : (string) $o, 'en' => is_array($o) ? ($o['en'] ?? null) : null])->filter(fn ($o) => $o['mk'] !== '')->values()->all(),
        ])->values()->all();
        if (! $questions) {
            throw new AiException(__('The AI did not return any questions. Please try again.'));
        }
        $types = array_values(array_intersect((array) ($out['offer_types'] ?? []), ['tour', 'event', 'accommodation', 'visa']));

        return ChatQuestionnaire::create([
            'agency_id' => $agencyId,
            'title' => ['mk' => $out['title']['mk'] ?? Str::limit($brief, 80), 'en' => $out['title']['en'] ?? null],
            'intro' => ['mk' => $out['intro']['mk'] ?? null, 'en' => $out['intro']['en'] ?? null],
            'icon' => mb_substr((string) ($out['icon'] ?? '✨'), 0, 4),
            'category' => array_key_exists($out['category'] ?? '', ChatQuestionnaire::categories()) ? $out['category'] : ($category ?: 'other'),
            'questions' => $questions,
            'offer_types' => $types ?: null,
            'is_active' => false,
            'made_by_ai' => true,
            'sort_order' => (int) ChatQuestionnaire::withoutGlobalScopes()->where('agency_id', $agencyId)->max('sort_order') + 1,
        ]);
    }
}
