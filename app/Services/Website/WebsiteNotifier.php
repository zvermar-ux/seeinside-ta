<?php

namespace App\Services\Website;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real-time sync: collects changed catalog items during a request and, after the response is sent,
 * POSTs one signed webhook per change to every connected website of that agency.
 *
 * Payload: {"event":"tour.updated","type":"tour","id":5,"slug":"...","updated_at":"..."}
 * Header:  X-TA-Signature: sha256=<hmac of the raw body with the client's webhook secret>
 */
class WebsiteNotifier
{
    /** @var array<string, array> */
    protected static array $pending = [];

    protected static bool $registered = false;

    public static bool $enabled = true;

    /** Destinations, groups or agency data changed (website refreshes /meta) */
    public static function metaChanged(?int $agencyId): void
    {
        static::listChanged($agencyId, 'meta');
    }

    /** A whole list the website keeps in one piece changed: meta, reviews, chat (live chat settings) */
    public static function listChanged(?int $agencyId, string $type): void
    {
        if (! static::$enabled || ! $agencyId) {
            return;
        }
        static::$pending[$type.':0:'.$agencyId] = ['agency_id' => $agencyId, 'event' => $type.'.updated', 'type' => $type, 'id' => 0, 'slug' => null, 'updated_at' => now()->toIso8601String()];
        if (! static::$registered) {
            static::$registered = true;
            app()->terminating(fn () => static::flush());
        }
    }

    public static function changed(Model $model): void
    {
        static::queue($model, 'updated');
    }

    public static function deleted(Model $model): void
    {
        static::queue($model, 'deleted');
    }

    protected static function queue(Model $model, string $action): void
    {
        if (! static::$enabled || ! $model->agency_id) {
            return;
        }

        $type = $model::websiteType();
        \App\Services\Ai\ChatCatalog::forget((int) $model->agency_id);
        static::$pending["{$type}:{$model->getKey()}"] = [
            'agency_id' => $model->agency_id,
            'event' => "{$type}.{$action}",
            'type' => $type,
            'id' => $model->getKey(),
            'slug' => $model->slug,
            'updated_at' => now()->toIso8601String(),
        ];

        if (! static::$registered) {
            static::$registered = true;
            app()->terminating(fn () => static::flush());
        }
    }

    public static function flush(): void
    {
        $pending = static::$pending;
        static::$pending = [];
        static::$registered = false;

        if (! $pending) {
            return;
        }

        $byAgency = collect($pending)->groupBy('agency_id');
        $clients = ApiClient::withoutGlobalScopes()
            ->whereIn('agency_id', $byAgency->keys())
            ->where('is_active', true)
            ->whereNotNull('webhook_url')
            ->get();

        foreach ($clients as $client) {
            foreach ($byAgency->get($client->agency_id, []) as $payload) {
                if ($client->push_mode) {
                    // the website cannot call the app: send the full record and its photos
                    WebsitePush::pushItem($client, $payload['type'], $payload['id'], $payload['slug'], str_ends_with($payload['event'], '.deleted') ? 'deleted' : 'updated');
                } else {
                    static::send($client, collect($payload)->except('agency_id')->all());
                }
            }
        }
    }

    public static function send(ApiClient $client, array $payload, int $timeout = 5): ?int
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $client->webhook_secret);

        try {
            $status = Http::timeout($timeout)->connectTimeout(3)
                ->withHeaders(['X-TA-Signature' => $signature, 'X-TA-Event' => $payload['event'] ?? 'ping'])
                ->withBody($body, 'application/json')
                ->post($client->webhook_url)
                ->status();
        } catch (Throwable $e) {
            Log::warning('Webhook failed', ['client' => $client->id, 'error' => $e->getMessage()]);
            $status = 0;
        }

        $client->forceFill(['last_webhook_at' => now(), 'last_webhook_status' => $status])->saveQuietly();

        return $status;
    }
}
