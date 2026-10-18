<?php

namespace App\Services\Ai;

use App\Models\AiAccount;
use App\Models\AiUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One door to Claude, ChatGPT and Gemini for the whole app.
 *  - every agency has its own keys, models and monthly limits (AiAccount); the super admin has his own
 *  - optionally an agency may use the platform keys (allowed and capped by the super admin)
 *  - every call is logged with its cost (AiUsage) and refused when the limit is reached
 *
 * Features and their budgets:  assistant, image, training → operator budget (assistant_limit)
 *                              chat                       → live chat budget (chat_limit)
 */
class Ai
{
    public const OPERATOR_FEATURES = ['assistant', 'image', 'training'];

    /* ======================== configuration ======================== */

    public static function account(?int $agencyId): AiAccount
    {
        return AiAccount::for($agencyId);
    }

    /** Which provider / model / key a feature uses for this agency */
    public static function resolve(?int $agencyId, string $feature): array
    {
        $acc = static::account($agencyId);
        [$enabled, $provider, $model] = match ($feature) {
            'image' => [$acc->exists ? $acc->images_enabled : true, $acc->image_provider ?: 'openai', $acc->image_model],
            'chat' => [$acc->exists ? $acc->chat_enabled : false, $acc->chat_provider ?: 'anthropic', $acc->chat_model],
            default => [$acc->exists ? $acc->assistant_enabled : true, $acc->assistant_provider ?: 'anthropic', $acc->assistant_model],
        };
        if (! $enabled) {
            throw new AiException($feature === 'chat' ? __('The AI chatbot is turned off.') : __('AI is turned off in Settings → AI.'));
        }
        $kind = $feature === 'image' ? 'image' : ($feature === 'chat' ? 'chat' : 'assistant');
        $model = $model ?: config("ai.defaults.{$kind}.{$provider}");

        $key = $acc->key($provider);
        $viaPlatform = false;
        if (! $key && $agencyId && $acc->platform_allowed) {
            $key = AiAccount::platform()->key($provider);
            $viaPlatform = (bool) $key;
        }
        if (! $key) {
            throw new AiException(__('No :provider API key. Add it in Settings → AI.', ['provider' => config("ai.providers.{$provider}", $provider)]));
        }

        return ['account' => $acc, 'provider' => $provider, 'model' => $model, 'key' => $key, 'platform' => $viaPlatform];
    }

    public static function available(?int $agencyId, string $feature): bool
    {
        try {
            static::resolve($agencyId, $feature);
            static::checkBudget($agencyId, $feature, null, false);

            return true;
        } catch (AiException) {
            return false;
        }
    }

    /* ======================== prices & limits ======================== */

    public static function textPrice(AiAccount $acc, string $provider, string $model): array
    {
        $own = $acc->prices[$model] ?? null;
        if (is_array($own) && isset($own[0], $own[1]) && is_numeric($own[0]) && is_numeric($own[1])) {
            return [(float) $own[0], (float) $own[1]];
        }
        if ($p = config("ai.text_models.{$provider}.{$model}")) {
            return $p;
        }
        // unknown model: assume an expensive one so the limit is never exceeded by surprise
        return [5.0, 25.0];
    }

    public static function imagePrice(AiAccount $acc, string $provider, string $model): float
    {
        $own = $acc->prices[$model] ?? null;
        if (is_numeric($own) || (is_array($own) && is_numeric($own[0] ?? null) && ! isset($own[1]))) {
            return (float) (is_array($own) ? $own[0] : $own);
        }

        return (float) (config("ai.image_models.{$provider}.{$model}") ?? 0.2);
    }

    public static function group(string $feature): string
    {
        return $feature === 'chat' ? 'chat' : 'assistant';
    }

    public static function features(string $group): array
    {
        return $group === 'chat' ? ['chat'] : static::OPERATOR_FEATURES;
    }

    public static function spent(?int $agencyId, string $group, ?int $userId = null, string $period = 'month', ?bool $viaPlatform = null): float
    {
        $from = $period === 'day' ? now()->startOfDay() : now()->startOfMonth();

        return (float) AiUsage::query()
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId), fn ($q) => $q->whereNull('agency_id'))
            ->when($group !== 'all', fn ($q) => $q->whereIn('feature', static::features($group)))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($viaPlatform !== null, fn ($q) => $q->where('via_platform', $viaPlatform))
            ->where('created_at', '>=', $from)
            ->sum('cost');
    }

    public static function limit(AiAccount $acc, string $group): ?float
    {
        $v = $group === 'chat' ? $acc->chat_limit : $acc->assistant_limit;

        return $v === null ? null : (float) $v;
    }

    /** Throws when the monthly (or daily per operator, or platform) limit is reached */
    public static function checkBudget(?int $agencyId, string $feature, ?int $userId = null, bool $resolveFirst = true): void
    {
        $acc = static::account($agencyId);
        $group = static::group($feature);
        $limit = static::limit($acc, $group);
        if ($limit !== null && static::spent($agencyId, $group) >= $limit) {
            throw new AiException($group === 'chat'
                ? __('The monthly AI limit for the live chat is used up.')
                : __('The monthly AI limit for the operator assistant is used up (:limit USD). The agency admin can raise it in Settings → AI.', ['limit' => number_format($limit, 2)]));
        }
        if ($group === 'assistant' && $userId && $acc->user_daily_limit !== null && static::spent($agencyId, 'assistant', $userId, 'day') >= (float) $acc->user_daily_limit) {
            throw new AiException(__('Your daily AI limit is used up. Try again tomorrow or ask the agency admin.'));
        }
        if ($agencyId && $acc->platform_allowed && ! $acc->key(static::providerFor($acc, $feature)) && $acc->platform_limit !== null
            && static::spent($agencyId, 'all', null, 'month', true) >= (float) $acc->platform_limit) {
            throw new AiException(__('The monthly AI allowance from the platform is used up. Add your own API key in Settings → AI.'));
        }
    }

    protected static function providerFor(AiAccount $acc, string $feature): string
    {
        return match ($feature) {
            'image' => $acc->image_provider ?: 'openai',
            'chat' => $acc->chat_provider ?: 'anthropic',
            default => $acc->assistant_provider ?: 'anthropic',
        };
    }

    protected static function log(array $r, ?int $agencyId, ?int $userId, string $feature, int $in, int $out, int $images, float $cost, string $note): void
    {
        AiUsage::create([
            'agency_id' => $agencyId, 'user_id' => $userId, 'feature' => $feature, 'provider' => $r['provider'], 'model' => $r['model'],
            'input_tokens' => $in, 'output_tokens' => $out, 'images' => $images, 'cost' => round($cost, 6),
            'via_platform' => $r['platform'], 'note' => mb_substr($note, 0, 190),
        ]);
        static::alerts($r['account'], $agencyId, static::group($feature));
    }

    /** E-mail at 80 % and 100 % of a monthly limit (once per month) */
    protected static function alerts(AiAccount $acc, ?int $agencyId, string $group): void
    {
        $limit = static::limit($acc, $group);
        $to = $acc->alert_email ?: $acc->agency?->email;
        if (! $limit || ! $acc->exists || ! $to) {
            return;
        }
        $pct = static::spent($agencyId, $group) / $limit * 100;
        $level = $pct >= 100 ? 100 : ($pct >= 80 ? 80 : 0);
        $sent = $acc->alerts_sent ?? [];
        $key = now()->format('Y-m').":{$group}:{$level}";
        if (! $level || in_array($key, $sent, true)) {
            return;
        }
        $acc->forceFill(['alerts_sent' => array_slice(array_merge($sent, [$key]), -24)])->saveQuietly();
        $what = $group === 'chat' ? 'Live chat (AI бот)' : 'AI помош за оператори';
        try {
            Mail::raw("{$what}: потрошени се {$level}% од месечниот лимит од {$limit} USD.\n\nЛимитот се менува во SeeInside → Подесувања → AI.", fn ($m) => $m->to($to)->subject("AI лимит: {$level}% ({$what})"));
        } catch (Throwable $e) {
            Log::warning('AI limit alert failed: '.$e->getMessage());
        }
    }

    /* ======================== text ======================== */

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  role: user | assistant
     */
    public static function text(?int $agencyId, string $feature, string $system, array $messages, int $maxTokens = 1500, bool $json = false, ?int $userId = null, string $note = ''): string
    {
        $userId ??= Auth::id();
        $r = static::resolve($agencyId, $feature);
        static::checkBudget($agencyId, $feature, $userId);

        [$text, $in, $out] = match ($r['provider']) {
            'openai' => static::openaiText($r, $system, $messages, $maxTokens, $json),
            'gemini' => static::geminiText($r, $system, $messages, $maxTokens, $json),
            default => static::anthropicText($r, $system, $messages, $maxTokens),
        };
        [$pin, $pout] = static::textPrice($r['account'], $r['provider'], $r['model']);
        $cost = ($in * $pin + $out * $pout) / 1_000_000;
        static::log($r, $agencyId, $userId, $feature, $in, $out, 0, $cost, $note);
        static::$lastCost = $cost;

        return trim($text);
    }

    public static float $lastCost = 0;

    /** Text call that must answer with a JSON object */
    public static function json(?int $agencyId, string $feature, string $system, array $messages, int $maxTokens = 2500, ?int $userId = null, string $note = ''): array
    {
        $raw = static::text($agencyId, $feature, $system."\n\nAnswer ONLY with one valid JSON object, no markdown, no comments.", $messages, $maxTokens, true, $userId, $note);
        $data = static::parseJson($raw);
        if ($data === null) {
            Log::warning('AI returned invalid JSON', ['raw' => mb_substr($raw, 0, 2000)]);
            throw new AiException(__('The AI answer could not be read. Please try again.'));
        }

        return $data;
    }

    public static function parseJson(string $raw): ?array
    {
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw)));
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $a = strpos($raw, '{');
            $b = strrpos($raw, '}');
            $data = $a !== false && $b > $a ? json_decode(substr($raw, $a, $b - $a + 1), true) : null;
        }

        return is_array($data) ? $data : null;
    }

    protected static function http(string $provider)
    {
        return Http::timeout((int) config('ai.timeout', 90))->connectTimeout(10)->acceptJson()->asJson()->retry(2, 1500, function ($e) {
            return $e instanceof ConnectionException || ($e->response?->status() ?? 0) === 429 || ($e->response?->status() ?? 0) >= 500;
        }, throw: false);
    }

    protected static function fail(string $provider, ?Response $res, ?Throwable $e = null): never
    {
        $name = config("ai.providers.{$provider}", $provider);
        if ($e) {
            throw new AiException(__(':provider is not reachable: :error', ['provider' => $name, 'error' => $e->getMessage()]));
        }
        $msg = $res?->json('error.message') ?? $res?->json('error.0.message') ?? $res?->json('message') ?? mb_substr((string) $res?->body(), 0, 300);
        $status = $res?->status();
        $hint = match (true) {
            in_array($status, [401, 403], true) => __('Check the API key.'),
            $status === 404 => __('Check the model name.'),
            $status === 429 => __('The provider limit or credit is used up.'),
            default => '',
        };
        throw new AiException(trim("{$name} ({$status}): {$msg} {$hint}"));
    }

    protected static function anthropicText(array $r, string $system, array $messages, int $max): array
    {
        try {
            $res = static::http('anthropic')->withHeaders(['x-api-key' => $r['key'], 'anthropic-version' => '2023-06-01'])
                ->post(config('ai.endpoints.anthropic').'/messages', [
                    'model' => $r['model'], 'max_tokens' => $max, 'system' => $system,
                    'messages' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']], $messages),
                ]);
        } catch (Throwable $e) {
            static::fail('anthropic', null, $e);
        }
        if (! $res->successful()) {
            static::fail('anthropic', $res);
        }
        $text = collect($res->json('content', []))->where('type', 'text')->pluck('text')->implode("\n");

        return [$text, (int) $res->json('usage.input_tokens', 0), (int) $res->json('usage.output_tokens', 0)];
    }

    protected static function openaiText(array $r, string $system, array $messages, int $max, bool $json): array
    {
        $body = [
            'model' => $r['model'],
            'messages' => array_merge([['role' => 'system', 'content' => $system]], array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']], $messages)),
            'max_completion_tokens' => $max,
        ];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        try {
            $res = static::http('openai')->withToken($r['key'])->post(config('ai.endpoints.openai').'/chat/completions', $body);
        } catch (Throwable $e) {
            static::fail('openai', null, $e);
        }
        if (! $res->successful()) {
            static::fail('openai', $res);
        }

        return [(string) $res->json('choices.0.message.content', ''), (int) $res->json('usage.prompt_tokens', 0), (int) $res->json('usage.completion_tokens', 0)];
    }

    protected static function geminiText(array $r, string $system, array $messages, int $max, bool $json): array
    {
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) $m['content']]]], $messages),
            'generationConfig' => array_filter(['maxOutputTokens' => $max, 'responseMimeType' => $json ? 'application/json' : null]),
        ];
        try {
            $res = static::http('gemini')->withHeaders(['x-goog-api-key' => $r['key']])
                ->post(config('ai.endpoints.gemini').'/models/'.rawurlencode($r['model']).':generateContent', $body);
        } catch (Throwable $e) {
            static::fail('gemini', null, $e);
        }
        if (! $res->successful()) {
            static::fail('gemini', $res);
        }
        $text = collect($res->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode("\n");
        $out = (int) $res->json('usageMetadata.candidatesTokenCount', 0) + (int) $res->json('usageMetadata.thoughtsTokenCount', 0);

        return [$text, (int) $res->json('usageMetadata.promptTokenCount', 0), $out];
    }

    /* ======================== images ======================== */

    /** @return string binary image (PNG / JPEG / WEBP) */
    public static function image(?int $agencyId, string $prompt, string $shape = 'landscape', ?int $userId = null, string $note = ''): string
    {
        $userId ??= Auth::id();
        $r = static::resolve($agencyId, 'image');
        static::checkBudget($agencyId, 'image', $userId);

        $binary = $r['provider'] === 'gemini' ? static::geminiImage($r, $prompt, $shape) : static::openaiImage($r, $prompt, $shape);
        static::log($r, $agencyId, $userId, 'image', 0, 0, 1, static::imagePrice($r['account'], $r['provider'], $r['model']), $note ?: mb_substr($prompt, 0, 120));

        return $binary;
    }

    protected static function openaiImage(array $r, string $prompt, string $shape): string
    {
        $size = ['landscape' => '1536x1024', 'portrait' => '1024x1536', 'square' => '1024x1024'][$shape] ?? '1536x1024';
        try {
            $res = static::http('openai')->timeout(180)->withToken($r['key'])->post(config('ai.endpoints.openai').'/images/generations', [
                'model' => $r['model'], 'prompt' => $prompt, 'size' => $size, 'n' => 1,
            ]);
        } catch (Throwable $e) {
            static::fail('openai', null, $e);
        }
        if (! $res->successful()) {
            static::fail('openai', $res);
        }
        $b64 = $res->json('data.0.b64_json');
        if (! $b64 && ($url = $res->json('data.0.url'))) {
            return (string) Http::timeout(60)->get($url)->body();
        }
        if (! $b64) {
            throw new AiException(__('No image was returned. Try a different description.'));
        }

        return base64_decode($b64);
    }

    protected static function geminiImage(array $r, string $prompt, string $shape): string
    {
        $ratio = ['landscape' => '16:9', 'portrait' => '9:16', 'square' => '1:1'][$shape] ?? '16:9';
        try {
            $res = static::http('gemini')->timeout(180)->withHeaders(['x-goog-api-key' => $r['key']])
                ->post(config('ai.endpoints.gemini').'/models/'.rawurlencode($r['model']).':generateContent', [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseModalities' => ['IMAGE'], 'imageConfig' => ['aspectRatio' => $ratio]],
                ]);
        } catch (Throwable $e) {
            static::fail('gemini', null, $e);
        }
        if (! $res->successful()) {
            static::fail('gemini', $res);
        }
        $part = collect($res->json('candidates.0.content.parts', []))->first(fn ($p) => isset($p['inlineData']['data']));
        if (! $part) {
            throw new AiException(__('No image was returned. Try a different description.'));
        }

        return base64_decode($part['inlineData']['data']);
    }

    /* ======================== overview ======================== */

    /** Spending this month for the settings page */
    public static function overview(?int $agencyId): array
    {
        $acc = static::account($agencyId);
        $out = [];
        foreach (['assistant', 'chat'] as $g) {
            $spent = static::spent($agencyId, $g);
            $limit = static::limit($acc, $g);
            $out[$g] = ['spent' => $spent, 'limit' => $limit, 'percent' => $limit ? min(100, round($spent / max($limit, 0.0001) * 100)) : null];
        }
        $out['platform'] = $agencyId ? static::spent($agencyId, 'all', null, 'month', true) : 0;

        return $out;
    }

    /** Quick check of a key: one tiny request */
    public static function test(?int $agencyId, string $provider): string
    {
        $acc = static::account($agencyId);
        $key = $acc->key($provider);
        if (! $key) {
            throw new AiException(__('No :provider API key. Add it in Settings → AI.', ['provider' => config("ai.providers.{$provider}")]));
        }
        $model = $provider === $acc->assistant_provider && $acc->assistant_model ? $acc->assistant_model : (string) config("ai.defaults.chat.{$provider}");
        $r = ['account' => $acc, 'provider' => $provider, 'model' => $model, 'key' => $key, 'platform' => false];
        [$text, $in, $out] = match ($provider) {
            'openai' => static::openaiText($r, 'Answer with one word.', [['role' => 'user', 'content' => 'Say OK']], 20, false),
            'gemini' => static::geminiText($r, 'Answer with one word.', [['role' => 'user', 'content' => 'Say OK']], 20, false),
            default => static::anthropicText($r, 'Answer with one word.', [['role' => 'user', 'content' => 'Say OK']], 10),
        };
        [$pin, $pout] = static::textPrice($acc, $provider, $model);
        static::log($r, $agencyId, Auth::id(), 'training', $in, $out, 0, ($in * $pin + $out * $pout) / 1_000_000, 'connection test');

        return $model.': '.Arr::first(explode("\n", $text) ?: ['OK']);
    }
}
