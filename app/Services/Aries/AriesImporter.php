<?php

namespace App\Services\Aries;

use App\Enums\GroupType;
use App\Enums\ObjectType;
use App\Models\Agency;
use App\Models\Charter;
use App\Models\Group;
use App\Models\ObjectImage;
use App\Models\SliderImage;
use App\Models\Subagent;
use App\Models\TourOperator;
use App\Models\TravelObject;
use App\Models\User;
use App\Support\Permissions;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time (re-runnable) migration of everything in the Aries administration into one agency.
 * Records are matched by their Aries ids, so running it again updates instead of duplicating.
 */
class AriesImporter
{
    public const SOURCE = 'aries';

    /** Aries object types ("vid") */
    protected const VIDS = [0, 1, 2];

    public array $stats = [];

    /** @var array<string, string> remote url => local relative path (queued downloads) */
    protected array $downloads = [];

    /** aries group id => our id, per type */
    protected array $groupMap = ['group' => [], 'destination' => []];

    /** aries turop => TourOperator id */
    protected array $operatorMap = [];

    protected ?Closure $log = null;

    protected ?Closure $progress = null;

    public function __construct(
        protected AriesClient $client,
        protected Agency $agency,
        protected bool $withImages = true,
        protected bool $withDetails = true,
    ) {}

    public function onLog(Closure $log): static
    {
        $this->log = $log;

        return $this;
    }

    /** $progress(string $label, int $total) returns a tick closure */
    public function onProgress(Closure $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    protected function log(string $message): void
    {
        if ($this->log) {
            ($this->log)($message);
        }
    }

    protected function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    protected function ticker(string $label, int $total): Closure
    {
        return $this->progress ? ($this->progress)($label, $total) : fn () => null;
    }

    public function run(array $only = []): array
    {
        $steps = [
            'operators' => fn () => $this->importOperators(),
            'groups' => fn () => $this->importGroups(),
            'objects' => fn () => $this->importObjects(),
            'charters' => fn () => $this->importCharters(),
            'subagents' => fn () => $this->importSubagents(),
            'users' => fn () => $this->importAdministrators(),
            'slider' => fn () => $this->importSlider(),
        ];

        foreach ($steps as $name => $step) {
            if ($only && ! in_array($name, $only, true)) {
                continue;
            }
            // objects need the operator and group maps
            if ($name === 'objects' && $only && ! in_array('operators', $only, true)) {
                $this->loadOperatorMap();
            }
            if ($name === 'objects' && $only && ! in_array('groups', $only, true)) {
                $this->loadGroupMap();
            }

            $this->log("→ {$name}");
            try {
                $step();
            } catch (Throwable $e) {
                $this->bump('errors');
                $this->log("  ! {$name}: ".$e->getMessage());
            }
        }

        if ($this->withImages && $this->downloads) {
            $this->flushDownloads();
        }

        return $this->stats;
    }

    /* ---------------------------------------------------------------- operators */

    protected function importOperators(): void
    {
        $options = AriesParser::options($this->client->command('turoperatori'));

        foreach ($options as $id => $name) {
            $isOwn = (string) $id === '0';
            $operator = TourOperator::withoutGlobalScopes()->updateOrCreate(
                ['agency_id' => $this->agency->id, 'external_source' => self::SOURCE, 'external_id' => (string) $id],
                ['name' => $isOwn || trim($name) === '/' ? __('Own offer') : $name, 'is_own' => $isOwn],
            );
            $this->operatorMap[(string) $id] = $operator->id;
            $this->bump('tour_operators');
        }
    }

    protected function loadOperatorMap(): void
    {
        $this->operatorMap = TourOperator::withoutGlobalScopes()
            ->where('agency_id', $this->agency->id)->where('external_source', self::SOURCE)
            ->pluck('id', 'external_id')->all();
    }

    /* ---------------------------------------------------------------- groups */

    protected function importGroups(): void
    {
        foreach ([0 => GroupType::Group, 1 => GroupType::Destination] as $grupatip => $type) {
            $rows = $this->client->grid("operacii.php?komanda=grupi&status=-1&grupatip={$grupatip}&grupa=");
            $tick = $this->ticker($type === GroupType::Group ? 'Groups' : 'Destinations', count($rows));
            $parents = [];

            foreach ($rows as $row) {
                $c = $row['cell'] ?? [];
                $id = (string) ($c['id'] ?? $row['id']);
                $name = AriesParser::text($c['grupa'] ?? null) ?? ('#'.$id);

                $description = null;
                if ($this->withDetails) {
                    $description = AriesParser::html($this->client->command('zemi-grupaopis', ['id' => $id, 'grupatip' => $grupatip]));
                }

                $image = null;
                if ($src = AriesParser::imgSrc($c['slika'] ?? null)) {
                    $image = $this->queueDownload($src, $type === GroupType::Group ? 'groups' : 'destinations');
                }

                $group = Group::withoutGlobalScopes()->firstOrNew([
                    'agency_id' => $this->agency->id,
                    'type' => $type->value,
                    'external_source' => self::SOURCE,
                    'external_id' => $id,
                ]);
                $group->setTranslation('name', 'mk', $name);
                if ($description) {
                    $group->setTranslation('description', 'mk', $description);
                }
                $group->fill([
                    'image' => $image ?? $group->image,
                    'sort_order' => (int) (AriesParser::value($c['rb'] ?? null) ?? 0),
                    'is_active' => AriesParser::checked($c['aktivna'] ?? ''),
                    'show_on_homepage' => AriesParser::checked($c['strana'] ?? ''),
                    'show_in_offer' => AriesParser::checked($c['ponuda'] ?? ''),
                    'use_external_url' => AriesParser::checked($c['nadvoresenlink'] ?? ''),
                    'external_url' => AriesParser::text($c['nlink'] ?? null),
                ]);
                $group->save();

                $this->groupMap[$type->value][$id] = $group->id;
                if (! empty($c['parentid']) && (string) $c['parentid'] !== '0') {
                    $parents[$group->id] = (string) $c['parentid'];
                }
                $this->bump($type === GroupType::Group ? 'groups' : 'destinations');
                $tick();
            }

            foreach ($parents as $groupId => $parentAriesId) {
                if ($parentId = $this->groupMap[$type->value][$parentAriesId] ?? null) {
                    Group::withoutGlobalScopes()->whereKey($groupId)->update(['parent_id' => $parentId]);
                }
            }
        }
    }

    protected function loadGroupMap(): void
    {
        foreach ([GroupType::Group, GroupType::Destination] as $type) {
            $this->groupMap[$type->value] = Group::withoutGlobalScopes()
                ->where('agency_id', $this->agency->id)->where('type', $type->value)->where('external_source', self::SOURCE)
                ->pluck('id', 'external_id')->all();
        }
    }

    /* ---------------------------------------------------------------- objects */

    protected function importObjects(): void
    {
        foreach ($this->operatorMap as $turop => $operatorId) {
            foreach (self::VIDS as $vid) {
                $rows = $this->client->grid("operacii.php?komanda=objekti&vid={$vid}&status=-1&objekt=&turop={$turop}");
                // Aries returns the total for all types but only rows for the requested one
                $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['cell']['vid'] ?? $vid) === (string) $vid));
                if (! $rows) {
                    continue;
                }

                $type = ObjectType::fromAries($vid);
                $tick = $this->ticker("Operator {$turop} · ".$type->getPluralLabel(), count($rows));
                $details = [];

                foreach ($rows as $row) {
                    $this->importObjectRow($row['cell'] ?? [], (string) $turop, $operatorId, $vid, $type, $details);
                    $tick();
                }
            }
        }
    }

    protected function importObjectRow(array $c, string $turop, int $operatorId, int $vid, ObjectType $type, array &$details): void
    {
        $id = (string) ($c['id'] ?? '');
        if ($id === '') {
            return;
        }
        $serviceId = (string) ($c['uslugaid'] ?? '0');
        $category = (int) ($c['category'] ?? 0);

        $object = TravelObject::withoutGlobalScopes()->firstOrNew([
            'agency_id' => $this->agency->id,
            'tour_operator_id' => $operatorId,
            'type' => $type->value,
            'external_source' => self::SOURCE,
            'external_id' => $id,
            'external_service_id' => $serviceId,
        ]);

        $object->fill([
            'name' => AriesParser::text($c['naziv'] ?? null) ?? ('#'.$id),
            'room' => AriesParser::text($c['soba'] ?? null),
            'board' => AriesParser::text($c['usluga'] ?? null),
            'stars' => ($category >= 1 && $category <= 5 && ($type !== ObjectType::Arrangement || $category > 1)) ? $category : null,
            'place' => AriesParser::text($c['mesto'] ?? null),
            'region' => AriesParser::text($c['region'] ?? null),
            'country' => AriesParser::text($c['drzava'] ?? null),
            'is_active' => AriesParser::checked($c['aktivna'] ?? ''),
            'is_featured' => AriesParser::checked($c['predlog'] ?? ''),
            'min_payment_percent' => AriesParser::percent($c['procentuplata'] ?? null) ?? 100,
            'markup_percent' => AriesParser::percent($c['markup'] ?? null) ?? 0,
            'group_id' => $this->groupMap['group'][(string) ($c['grupaid'] ?? '')] ?? null,
            'destination_id' => $this->groupMap['destination'][(string) ($c['destinacijaid'] ?? '')] ?? null,
            'synced_at' => now(),
        ]);

        // Descriptions, map and gallery are per Aries object, shared by its room/service rows
        if ($this->withDetails) {
            $key = "{$turop}|{$vid}|{$id}";
            $details[$key] ??= $this->fetchDetails($id, $vid, $turop);
            $d = $details[$key];

            if ($d['description']) {
                $object->setTranslation('description', 'mk', $d['description']);
            }
            if ($d['map']) {
                $object->map_location = $d['map'];
            }

            if ($serviceId !== '0' && $serviceId !== '') {
                $svc = AriesParser::html($this->client->command('zemi-uslugaopis', ['id' => $id, 'uslugaid' => $serviceId, 'vid' => $vid, 'turop' => $turop]));
                if ($svc) {
                    $object->setTranslation('service_description', 'mk', $svc);
                }
            }
        }

        $isNew = ! $object->exists;
        $object->save();
        $this->bump($isNew ? 'objects_created' : 'objects_updated');

        if ($this->withDetails) {
            $gallery = $d['images'];
            if ($vid === 2 && $serviceId !== '0') {
                $gallery = array_merge($gallery, AriesParser::gallery($this->client->command('sliki-lista', ['id' => $id, 'uslugaid' => $serviceId, 'vid' => $vid, 'turop' => $turop])));
            }
            $this->syncImages($object, $gallery);
        }
    }

    protected function fetchDetails(string $id, int $vid, string $turop): array
    {
        $p = ['id' => $id, 'vid' => $vid, 'turop' => $turop];

        $map = AriesParser::text($this->client->command('zemi-mapa', $p));

        return [
            'description' => AriesParser::html($this->client->command('zemi-opis', $p)),
            'map' => $map ? Str::limit($map, 250, '') : null,
            'images' => AriesParser::gallery($this->client->command('sliki-lista', $p)),
        ];
    }

    protected function syncImages(TravelObject $object, array $gallery): void
    {
        if (! $gallery) {
            return;
        }

        $existing = ObjectImage::where('travel_object_id', $object->id)->pluck('id', 'external_id');
        $sort = 0;

        foreach ($gallery as $img) {
            $path = $this->queueDownload($img['src'], 'objects');
            $sort++;

            $data = ['path' => $path, 'caption' => $img['caption'], 'is_main' => $img['is_main'], 'sort_order' => $sort];

            if ($img['external_id'] && isset($existing[$img['external_id']])) {
                ObjectImage::whereKey($existing[$img['external_id']])->update($data);
            } else {
                ObjectImage::withoutEvents(fn () => ObjectImage::create($data + [
                    'travel_object_id' => $object->id,
                    'external_id' => $img['external_id'],
                ]));
                $this->bump('images');
            }
        }

        $object->forceFill(['cover_image' => collect($gallery)->firstWhere('is_main', true) ? $this->localPath(collect($gallery)->firstWhere('is_main', true)['src'], 'objects') : null])->saveQuietly();
    }

    /* ---------------------------------------------------------------- charters */

    protected function importCharters(): void
    {
        $rows = $this->client->grid('operacii.php?komanda=carteri&aktiven=-1&region=');

        foreach ($rows as $row) {
            $c = $row['cell'] ?? [];
            $id = (string) ($c['id'] ?? $row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            Charter::withoutGlobalScopes()->updateOrCreate(
                ['agency_id' => $this->agency->id, 'external_source' => self::SOURCE, 'external_id' => $id],
                [
                    'region' => AriesParser::value($c['region'] ?? null) ?? '—',
                    'departure_date' => AriesParser::date($c['poaganje'] ?? null),
                    'return_date' => AriesParser::date($c['vrakanje'] ?? null),
                    'seats' => (int) (AriesParser::value($c['mesta'] ?? null) ?? 0),
                    'currency' => strtoupper(substr(AriesParser::value($c['valuta'] ?? null) ?? 'EUR', 0, 3)),
                    'price' => (float) str_replace(',', '.', AriesParser::value($c['cena'] ?? null) ?? '0'),
                    'transport_only_price' => ($v = AriesParser::value($c['cenasamoprevoz'] ?? null)) !== null ? (float) str_replace(',', '.', $v) : null,
                    'departure_place' => AriesParser::value($c['opis1'] ?? null),
                    'destination' => AriesParser::value($c['opis2'] ?? null),
                    'note' => AriesParser::value($c['opis3'] ?? null),
                    'is_active' => AriesParser::checked($c['aktiven'] ?? ''),
                ],
            );
            $this->bump('charters');
        }
    }

    /* ---------------------------------------------------------------- subagents */

    protected function importSubagents(): void
    {
        $rows = $this->client->grid('pristap_subagenti.php?firma=&lice=&pristap=0&saldo=0');

        foreach ($rows as $row) {
            $c = $row['cell'] ?? [];
            $id = (string) ($c['licesr'] ?? '');
            if ($id === '') {
                continue;
            }

            Subagent::withoutGlobalScopes()->updateOrCreate(
                ['agency_id' => $this->agency->id, 'external_source' => self::SOURCE, 'external_id' => $id],
                [
                    'company' => AriesParser::text($c['firma'] ?? null) ?? AriesParser::text($c['lice'] ?? null) ?? '—',
                    'contact_person' => AriesParser::text($c['lice'] ?? null),
                    'username' => AriesParser::value($c['korisnik'] ?? null),
                    'email' => AriesParser::value($c['email'] ?? null),
                    'has_access' => AriesParser::checked($c['pristap'] ?? ''),
                    'no_balance_limit' => AriesParser::checked($c['bezsaldo'] ?? ''),
                    'external_company_id' => isset($c['sr']) ? (string) $c['sr'] : null,
                ],
            );
            $this->bump('subagents');
        }
    }

    /* ---------------------------------------------------------------- administrators -> users */

    protected function importAdministrators(): void
    {
        $rows = $this->client->grid('pristap_administratori.php?user=');

        foreach ($rows as $row) {
            $c = $row['cell'] ?? [];
            $username = AriesParser::value($c['korisnik'] ?? null);
            $email = AriesParser::value($c['email'] ?? null);

            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("  · skipped administrator {$username}: no valid e-mail ({$email})");
                continue;
            }
            if (User::where('email', $email)->exists()) {
                continue;
            }

            // Accounts are created inactive with a random password: the admin sets a password and activates them.
            $user = User::create([
                'agency_id' => $this->agency->id,
                'name' => $username ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(24),
                'is_active' => false,
                'locale' => 'mk',
            ]);
            $user->syncRoles([Permissions::AGENCY_ADMIN]);
            $user->syncPermissions(Permissions::roleDefaults(Permissions::AGENCY_ADMIN));
            $this->bump('users_created');
            $this->log("  · user {$email} created (inactive – set a password in Users)");
        }
    }

    /* ---------------------------------------------------------------- homepage slider */

    protected function importSlider(): void
    {
        $html = $this->client->command('pocetna-sliki');
        preg_match_all('/<img[^>]+src\s*=\s*(["\'])(.*?)\1/is', $html, $m);

        $sort = 0;
        foreach (array_unique($m[2] ?? []) as $src) {
            if (trim($src) === '') {
                continue;
            }
            $path = $this->queueDownload($src, 'slider');
            SliderImage::withoutGlobalScopes()->firstOrCreate(
                ['agency_id' => $this->agency->id, 'path' => $path],
                ['sort_order' => ++$sort, 'is_active' => true],
            );
            $this->bump('slider_images');
        }
    }

    /* ---------------------------------------------------------------- files */

    /** Local path (relative to the public disk) for an Aries image */
    protected function localPath(string $src, string $folder): string
    {
        $url = $this->client->absoluteUrl($src);
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $name = basename($path);
        $sub = trim(dirname($path), '/');
        // keep the operator sub-folder (e.g. hoteli/1000001) to avoid name clashes
        $sub = Str::of($sub)->replace(['konfiguracija/', '..'], '')->slug('/')->toString();

        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: md5($name);

        return "aries/{$folder}/".($sub ? str_replace('/', '-', $sub).'/' : '')."{$base}.".strtolower($ext);
    }

    protected function queueDownload(string $src, string $folder): string
    {
        $local = $this->localPath($src, $folder);

        if ($this->withImages && ! Storage::disk('public')->exists($local)) {
            $this->downloads[$this->client->absoluteUrl($src)] = $local;
        }

        return $local;
    }

    protected function flushDownloads(): void
    {
        $files = [];
        foreach ($this->downloads as $url => $local) {
            $files[$url] = Storage::disk('public')->path($local);
        }

        $tick = $this->ticker('Images', count($files));
        $saved = 0;
        foreach (array_chunk($files, 40, true) as $chunk) {
            $saved += $this->client->download($chunk);
            for ($i = 0; $i < count($chunk); $i++) {
                $tick();
            }
        }

        $this->bump('files_downloaded', $saved);
        $missing = count($files) - $saved;
        if ($missing > 0) {
            $this->bump('files_missing', $missing);
        }
        $this->downloads = [];
    }
}
