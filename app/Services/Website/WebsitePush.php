<?php

namespace App\Services\Website;

use App\Http\Controllers\Api\CatalogController;
use App\Models\Accommodation;
use App\Models\ApiClient;
use App\Models\Event;
use App\Models\Tour;
use App\Models\VisaType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * "Push mode" for websites whose hosting blocks outgoing connections to the app:
 * every webhook carries the full record (same JSON as the API) and the photos are uploaded to the
 * website before the record, so the website never has to call the app.
 */
class WebsitePush
{
    /** Same data the website would get from the API (null = not published) */
    public static function data(int $agencyId, string $type, ?string $slug = null): ?array
    {
        $request = Request::create('/api/v1/push');
        $request->attributes->set('agency_id', $agencyId);
        $c = app(CatalogController::class);

        try {
            $res = match ($type) {
                'tour' => $c->tour($request, (string) $slug),
                'event' => $c->event($request, (string) $slug),
                'accommodation' => $c->accommodation($request, (string) $slug),
                'visa' => $c->visa($request, (string) $slug),
                'slider' => $c->slider($request),
                'charter' => $c->charters($request),
                'meta' => $c->meta($request),
                'reviews' => $c->reviews($request),
                'chat' => $c->chat($request),
                default => null,
            };
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return null;
        }
        if (! $res) {
            return null;
        }
        $json = $res->getData(true);

        return $type === 'meta' ? $json : ($json['data'] ?? null);
    }

    protected static function sign(ApiClient $client, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, (string) $client->webhook_secret);
    }

    /** POST signed JSON to /wp-json/seeinside-travel/v1/<path> next to the webhook */
    public static function post(ApiClient $client, string $path, array $payload, int $timeout = 30): array
    {
        $url = preg_replace('#/webhook/?$#', '/'.$path, (string) $client->webhook_url);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            $res = Http::timeout($timeout)->connectTimeout(5)
                ->withHeaders(['X-TA-Signature' => static::sign($client, $body)])
                ->withBody($body, 'application/json')->post($url);

            return [$res->status(), $res->json()];
        } catch (Throwable $e) {
            Log::warning('Website push failed', ['client' => $client->id, 'path' => $path, 'error' => $e->getMessage()]);

            return [0, null];
        }
    }

    /** All photo URLs in a record */
    public static function imageUrls(?array $data): array
    {
        if (! $data) {
            return [];
        }
        $urls = [$data['image'] ?? null];
        foreach ($data['gallery'] ?? [] as $g) {
            $urls[] = $g['url'] ?? null;
        }
        foreach ($data['rooms'] ?? [] as $r) {
            $urls[] = $r['cover_image'] ?? null;
            foreach ($r['images'] ?? [] as $g) {
                $urls[] = $g['url'] ?? null;
            }
        }
        foreach ($data['slider'] ?? (array_is_list($data) ? $data : []) as $s) {
            $urls[] = is_array($s) ? ($s['image'] ?? null) : null;
        }
        foreach ($data['info_cards'] ?? [] as $c) {
            $urls[] = $c['image'] ?? null;
        }
        foreach (['destinations', 'groups'] as $k) {
            foreach ($data[$k] ?? [] as $g) {
                $urls[] = $g['image'] ?? null;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /** Upload the photos the website does not have yet */
    public static function pushImages(ApiClient $client, array $urls): void
    {
        if (! $urls) {
            return;
        }
        [$code, $res] = static::post($client, 'media-check', ['urls' => $urls], 15);
        if ($code !== 200) {
            return;
        }
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';
        foreach ((array) ($res['missing'] ?? []) as $url) {
            if (! is_string($url) || ! str_starts_with($url, $base)) {
                continue;   // only our own files
            }
            $path = rawurldecode(substr($url, strlen($base)));
            if (! Storage::disk('public')->exists($path) || Storage::disk('public')->size($path) > 15 * 1024 * 1024) {
                continue;
            }
            static::post($client, 'media', [
                'url' => $url,
                'name' => basename($path),
                'content' => base64_encode(Storage::disk('public')->get($path)),
            ], 60);
        }
    }

    /** Send one item (or list) with its photos */
    public static function pushItem(ApiClient $client, string $type, ?int $id, ?string $slug, string $action = 'updated'): int
    {
        $data = $action === 'deleted' ? null : static::data($client->agency_id, $type, $slug);
        if ($data !== null) {
            static::pushImages($client, static::imageUrls($data));
        }

        return (int) WebsiteNotifier::send($client, [
            'event' => $type.'.'.($action === 'deleted' ? 'deleted' : ($data === null && ! in_array($type, ['slider', 'charter', 'meta', 'reviews', 'chat'], true) ? 'unpublished' : 'updated')),
            'type' => $type,
            'id' => $id,
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
            'data' => $data,
        ], 30);
    }

    /** Everything published, in order: destinations & agency, slider, charters, offers */
    public static function pushAll(ApiClient $client): array
    {
        @set_time_limit(0);
        $stats = ['meta' => static::pushItem($client, 'meta', 0, null), 'slider' => static::pushItem($client, 'slider', 0, null), 'charter' => static::pushItem($client, 'charter', 0, null), 'reviews' => static::pushItem($client, 'reviews', 0, null), 'chat' => static::pushItem($client, 'chat', 0, null)];
        foreach (['tour' => Tour::class, 'event' => Event::class, 'accommodation' => Accommodation::class, 'visa' => VisaType::class] as $type => $class) {
            $ok = 0;
            foreach ($class::withoutGlobalScopes()->where('agency_id', $client->agency_id)->where('is_active', true)->get(['id', 'slug']) as $m) {
                $status = static::pushItem($client, $type, $m->id, $m->slug);
                $ok += $status >= 200 && $status < 300 ? 1 : 0;
            }
            $stats[$type] = $ok;
        }
        $client->forceFill(['last_full_push_at' => now()])->saveQuietly();

        return $stats;
    }
}
