<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatQuestionnaire;
use App\Services\Ai\ChatBot;
use App\Services\Ai\ChatConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/** Live chat widget on the website (called from the visitor's browser) */
class ChatController extends Controller
{
    protected function agencyId(Request $request): int
    {
        return (int) $request->attributes->get('agency_id');
    }

    public function config(Request $request): JsonResponse
    {
        return response()->json(['data' => ChatConfig::forWebsite($this->agencyId($request))]);
    }

    protected function conversation(Request $request): ChatConversation
    {
        $data = $request->validate(['conversation' => 'required|integer', 'token' => 'required|string|size:48']);
        $conv = ChatConversation::withoutGlobalScopes()->where('agency_id', $this->agencyId($request))->find($data['conversation']);
        abort_if(! $conv || ! hash_equals($conv->token, $data['token']), 404, 'Conversation not found.');

        return $conv;
    }

    protected static function out(ChatMessage $m): array
    {
        return [
            'id' => $m->id, 'role' => $m->role, 'body' => $m->body, 'items' => $m->items ?? [],
            'name' => $m->role === 'operator' ? ($m->user?->name ? Str::before($m->user->name, ' ') : null) : null,
            'at' => $m->created_at?->toIso8601String(),
        ];
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitor' => 'required|uuid', 'locale' => 'nullable|in:mk,en', 'page_url' => 'nullable|string|max:500',
        ]);
        $agencyId = $this->agencyId($request);
        $c = ChatConfig::get($agencyId);
        abort_unless($c['enabled'], 403, 'Chat is turned off.');

        // the same browser continues its open conversation
        $conv = ChatConversation::withoutGlobalScopes()->where('agency_id', $agencyId)->where('visitor_id', $data['visitor'])
            ->where('status', 'open')->where('channel', 'ai')->where('updated_at', '>=', now()->subDays(7))->latest('id')->first();
        if (! $conv) {
            // simple protection: max 20 new conversations per IP per day
            $n = ChatConversation::withoutGlobalScopes()->where('agency_id', $agencyId)->where('ip', $request->ip())->where('created_at', '>=', now()->subDay())->count();
            abort_if($n >= 20, 429, 'Too many conversations.');
            $conv = ChatConversation::create([
                'agency_id' => $agencyId, 'visitor_id' => $data['visitor'], 'token' => Str::random(48), 'channel' => 'ai',
                'locale' => $data['locale'] ?? 'mk', 'page_url' => $data['page_url'] ?? null, 'ip' => $request->ip(),
            ]);
            $conv->messages()->create(['role' => 'bot', 'body' => $c['greeting'][$conv->locale] ?? $c['greeting']['mk'] ?? '']);
        }

        return response()->json(['data' => [
            'conversation' => $conv->id, 'token' => $conv->token,
            'messages' => $conv->messages()->with('user:id,name')->get()->map(fn ($m) => static::out($m))->all(),
            'human' => $conv->bot_paused,
        ]]);
    }

    public function message(Request $request): JsonResponse
    {
        $conv = $this->conversation($request);
        $text = trim((string) $request->validate(['text' => 'required|string|max:1500'])['text']);
        $recent = $conv->messages()->where('role', 'visitor')->where('created_at', '>=', now()->subHour())->count();
        abort_if($recent >= 40, 429, 'Too many messages. Please call us.');

        $visitor = $conv->messages()->create(['role' => 'visitor', 'body' => strip_tags($text)]);
        $conv->forceFill(['last_message_at' => now(), 'messages_count' => $conv->messages_count + 1, 'status' => 'open', 'page_url' => $request->input('page_url', $conv->page_url)])->save();

        $new = [static::out($visitor)];
        if (! $conv->bot_paused) {
            $reply = ChatBot::reply($conv);
            if ($reply) {
                $new[] = static::out($reply);
            }
        }

        return response()->json(['data' => ['messages' => $new, 'human' => $conv->fresh()->bot_paused]]);
    }

    /** New messages after an id (operator answers) */
    public function poll(Request $request): JsonResponse
    {
        $conv = $this->conversation($request);
        $after = (int) $request->query('after', $request->input('after', 0));

        return response()->json(['data' => [
            'messages' => $conv->messages()->with('user:id,name')->where('id', '>', $after)->get()->map(fn ($m) => static::out($m))->all(),
            'human' => $conv->bot_paused,
        ]]);
    }

    public function questionnaire(Request $request): JsonResponse
    {
        $conv = $this->conversation($request);
        $data = $request->validate(['questionnaire' => 'required|integer', 'answers' => 'required|array|max:30', 'answers.*' => 'nullable']);
        $q = ChatQuestionnaire::withoutGlobalScopes()->where('agency_id', $conv->agency_id)->where('is_active', true)->findOrFail($data['questionnaire']);
        $answers = [];
        foreach ($q->questions ?? [] as $x) {
            $v = $data['answers'][$x['key'] ?? ''] ?? null;
            $answers[$x['key']] = is_array($v) ? array_map(fn ($s) => Str::limit(strip_tags((string) $s), 200, ''), array_slice($v, 0, 10)) : Str::limit(strip_tags((string) $v), 500, '');
        }
        $before = $conv->messages()->max('id');
        ChatBot::recommend($conv, $q, $answers);

        return response()->json(['data' => [
            'messages' => $conv->messages()->with('user:id,name')->where('id', '>', $before)->get()->map(fn ($m) => static::out($m))->all(),
        ]]);
    }

    /** Visitor leaves contact / asks for a person */
    public function contact(Request $request): JsonResponse
    {
        $conv = $this->conversation($request);
        $data = $request->validate(['name' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:150', 'note' => 'nullable|string|max:1000']);
        abort_if(blank($data['phone'] ?? null) && blank($data['email'] ?? null), 422, 'Phone or e-mail is required.');
        $conv->forceFill(array_filter([
            'name' => isset($data['name']) ? strip_tags($data['name']) : null, 'phone' => isset($data['phone']) ? strip_tags($data['phone']) : null, 'email' => $data['email'] ?? null,
        ]) + ['needs_human' => true, 'last_message_at' => now()])->save();
        $text = trim(($conv->locale === 'en' ? '📞 Please contact me' : '📞 Ве молам контактирајте ме').': '.implode(', ', array_filter([$conv->name, $conv->phone, $conv->email])).(filled($data['note'] ?? null) ? "\n".strip_tags($data['note']) : ''));
        $m = $conv->messages()->create(['role' => 'visitor', 'body' => $text]);
        $ok = $conv->messages()->create(['role' => 'system', 'body' => $conv->locale === 'en' ? 'Thank you! An agent will contact you soon.' : 'Ви благодариме! Наш агент ќе ве контактира наскоро.']);

        $agency = $conv->agency;
        $to = $agency?->booking_notify_email ?: $agency?->email;
        if ($to) {
            try {
                \Illuminate\Support\Facades\Mail::raw("Посетител од live chat бара контакт:\n\n{$text}\n\nРазговор #{$conv->id} – SeeInside → AI & Live chat → Разговори.", fn ($mm) => $mm->to($to)->subject('Live chat: барање за контакт'));
            } catch (\Throwable) {
            }
        }

        return response()->json(['data' => ['messages' => [static::out($m), static::out($ok)]]]);
    }

    public function close(Request $request): JsonResponse
    {
        $conv = $this->conversation($request);
        $conv->forceFill(['status' => 'closed'])->save();

        return response()->json(['data' => ['ok' => true]]);
    }
}
