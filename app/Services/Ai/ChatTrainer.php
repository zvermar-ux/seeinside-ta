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
    public static function websiteText(int $agencyId): array
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
        $pages[$base.'/'] = static::clean($home);
        preg_match_all('/href=["\']([^"\'#?]+)["\']/i', $home, $m);
        $want = '/(kontakt|contact|za-nas|about|uslovi|terms|plakjanje|placanje|payment|faq|prasanja|cesto|osiguruvanje|insurance|vizi|visa|politika|privacy)/i';
        $links = collect($m[1])->map(fn ($u) => str_starts_with($u, '/') ? $base.$u : $u)
            ->filter(fn ($u) => parse_url($u, PHP_URL_HOST) === $host && preg_match($want, rawurldecode($u)))
            ->unique()->take(8);
        foreach ($links as $u) {
            if ($html = $get($u)) {
                $pages[$u] = static::clean($html);
            }
        }

        return $pages;
    }

    protected static function clean(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|nav|footer|header)[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|p|div|li|h[1-6])[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/[ \t]+/', ' ', $text))), 6000);
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
