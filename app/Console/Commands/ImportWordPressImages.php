<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\LibraryFile;
use App\Services\Media\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brings the images of a WordPress site into the media library – only the ORIGINAL of every image,
 * never the size variants WordPress makes (photo-300x200.jpg, photo-scaled.jpg, photo.jpg.webp …).
 *
 *   php artisan library:import-wordpress --url=https://mysite.mk --agency=1         (over the internet, nothing to copy)
 *   php artisan library:import-wordpress --dir=/path/to/uploads --agency=1          (a copied uploads folder)
 *   php artisan library:import-wordpress --dir=/path/to/uploads.zip --agency=1      (a zip of the uploads folder)
 */
class ImportWordPressImages extends Command
{
    protected $signature = 'library:import-wordpress
        {--url= : address of the WordPress site (reads the media list over the WordPress REST API)}
        {--dir= : uploads folder or .zip of it}
        {--agency= : agency id the images belong to (empty = shared library)}
        {--folder=wordpress : folder in the library}
        {--min-kb=3 : skip smaller files (icons, spacers)}
        {--dry : only count, copy nothing}';

    protected $description = 'Import the original images (no size variants) of a WordPress site into the media library';

    /** folders of plugins / caches / backups inside uploads – never content */
    protected const SKIP_DIRS = ['cache', 'elementor', 'wpforms', 'woocommerce_uploads', 'wc-logs', 'et-cache', 'backups', 'backup', 'updraft', 'ai1wm-backups',
        'wp-file-manager', 'imagify-backup', 'shortpixelbackups', 'ewww', 'smush', 'webp-express', 'wpo-cache', 'litespeed', 'sucuri', 'wflogs', 'gravity_forms', 'fonts', 'sta-media'];

    protected array $hashes = [];

    protected int $added = 0;

    protected int $skipped = 0;

    protected int $dupes = 0;

    public function handle(): int
    {
        @set_time_limit(0);
        $agencyId = $this->option('agency') ? (int) $this->option('agency') : null;
        if ($agencyId && ! Agency::find($agencyId)) {
            $this->error('Agency '.$agencyId.' not found.');

            return self::FAILURE;
        }
        $this->info('Checking the images already in the library (no duplicates)…');
        $disk = Storage::disk('public');
        foreach (LibraryFile::query()->pluck('path') as $p) {
            try {
                if ($disk->exists($p)) {
                    $this->hashes[md5_file($disk->path($p))] = true;
                }
            } catch (\Throwable) {
            }
        }

        if ($this->option('url')) {
            $this->fromSite(rtrim((string) $this->option('url'), '/'), $agencyId);
        } elseif ($this->option('dir')) {
            $this->fromDir((string) $this->option('dir'), $agencyId);
        } else {
            $this->error('Give --url=https://site.mk or --dir=/path/to/uploads');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(($this->option('dry') ? 'Would add: ' : 'Added: ').$this->added.' · same image already in the library: '.$this->dupes.' · skipped (variants, small, other files): '.$this->skipped);

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------ folder / zip */

    /** photo-300x200.jpg, photo-scaled.jpg, photo-rotated.jpg, photo-150x150@2x.jpg, photo.jpg.webp → variants */
    public static function isVariant(string $file, array $siblings): bool
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (preg_match('/-\d{2,5}x\d{2,5}(@\dx)?$/', $name)) {
            return true;
        }
        // converted copies made by optimisation plugins: photo.jpg.webp / photo.png.avif
        if (in_array($ext, ['webp', 'avif'], true) && preg_match('/\.(jpe?g|png|gif)$/i', $name)) {
            return true;
        }
        $dir = dirname($file);
        foreach (['-scaled', '-rotated'] as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $base = substr($name, 0, -strlen($suffix));
                foreach (['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG'] as $e) {
                    if (isset($siblings[$dir.'/'.$base.'.'.$e])) {
                        return true;    // the original is there
                    }
                }
            }
        }
        // photo.webp next to photo.jpg (WebP copy of the same picture)
        if (in_array($ext, ['webp', 'avif'], true)) {
            foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $e) {
                if (isset($siblings[$dir.'/'.$name.'.'.$e])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function fromDir(string $dir, ?int $agencyId): void
    {
        $tmp = null;
        if (str_ends_with(strtolower($dir), '.zip')) {
            $tmp = storage_path('app/wp-import-'.Str::random(6));
            $zip = new \ZipArchive;
            if ($zip->open($dir) !== true) {
                $this->error('Cannot open '.$dir);

                return;
            }
            $this->info('Unpacking the zip…');
            $zip->extractTo($tmp);
            $zip->close();
            $dir = $tmp;
        }
        $dir = rtrim($dir, '/');
        if (! is_dir($dir)) {
            $this->error('Folder not found: '.$dir);

            return;
        }
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $files[str_replace('\\', '/', $f->getPathname())] = true;
            }
        }
        $this->info(count($files).' files in the folder.');
        $bar = $this->output->createProgressBar(count($files));
        foreach (array_keys($files) as $path) {
            $bar->advance();
            $rel = ltrim(substr($path, strlen($dir)), '/');
            $top = strtolower(Str::before($rel, '/'));
            if (in_array($top, self::SKIP_DIRS, true) || ! MediaLibrary::isImage($path) || self::isVariant($path, $files)) {
                $this->skipped++;

                continue;
            }
            // keep the year/month folders of WordPress (2024/05/photo.jpg); files directly in uploads too
            $this->store(file_get_contents($path), $rel, $agencyId, null);
        }
        $bar->finish();
        if ($tmp) {
            \Illuminate\Support\Facades\File::deleteDirectory($tmp);
        }
    }

    /* ------------------------------------------------------------ over the internet (REST API) */

    protected function fromSite(string $url, ?int $agencyId): void
    {
        $page = 1;
        $items = [];
        $this->info('Reading the media list of '.$url.' …');
        do {
            $r = Http::timeout(60)->acceptJson()->withHeaders(['User-Agent' => 'SeeInside-Travel-Import'])
                ->get($url.'/wp-json/wp/v2/media', ['per_page' => 100, 'page' => $page, 'media_type' => 'image', '_fields' => 'id,source_url,media_details,alt_text,title']);
            if ($r->status() === 400 && $page > 1) {
                break;      // past the last page
            }
            if (! $r->successful()) {
                $this->error('WordPress answered HTTP '.$r->status().' – the REST API may be blocked. Use --dir with a copy of the uploads folder instead.');

                return;
            }
            foreach ((array) $r->json() as $m) {
                $src = (string) ($m['source_url'] ?? '');
                if (! $src) {
                    continue;
                }
                // big photos: WordPress shows "photo-scaled.jpg", the original is in media_details.original_image
                $orig = $m['media_details']['original_image'] ?? null;
                if ($orig) {
                    $src = substr($src, 0, strrpos($src, '/') + 1).rawurlencode($orig);
                }
                $items[] = ['url' => $src, 'alt' => trim(strip_tags((string) ($m['alt_text'] ?? ''))), 'title' => html_entity_decode(strip_tags((string) ($m['title']['rendered'] ?? '')))];
            }
            $pages = (int) $r->header('X-WP-TotalPages');
            $page++;
        } while ($page <= max(1, $pages));

        $this->info(count($items).' images on the site.');
        $bar = $this->output->createProgressBar(count($items));
        // 8 downloads at a time
        foreach (array_chunk($items, 8) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(fn ($it) => $pool->timeout(120)->withHeaders(['User-Agent' => 'SeeInside-Travel-Import'])->get($it['url']), $chunk));
            foreach ($chunk as $k => $it) {
                $bar->advance();
                $res = $responses[$k] ?? null;
                if (! $res instanceof \Illuminate\Http\Client\Response || ! $res->successful()) {
                    $this->skipped++;

                    continue;
                }
                $rel = preg_replace('~^.*/wp-content/uploads/~', '', rawurldecode(parse_url($it['url'], PHP_URL_PATH) ?: basename($it['url'])));
                $this->store($res->body(), $rel, $agencyId, $it['alt'] ?: $it['title'], $it['title']);
            }
        }
        $bar->finish();
    }

    /* ------------------------------------------------------------ */

    protected function store(string $bytes, string $rel, ?int $agencyId, ?string $alt, ?string $title = null): void
    {
        if (strlen($bytes) < (int) $this->option('min-kb') * 1024) {
            $this->skipped++;

            return;
        }
        $hash = md5($bytes);
        if (isset($this->hashes[$hash])) {
            $this->dupes++;

            return;
        }
        $this->hashes[$hash] = true;
        if ($this->option('dry')) {
            $this->added++;

            return;
        }
        $disk = Storage::disk('public');
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $dirPart = trim(dirname($rel), '/.');
        $base = Str::slug(pathinfo($rel, PATHINFO_FILENAME), '-', 'mk') ?: Str::random(8);
        $path = trim($this->option('folder'), '/').'/'.($dirPart ? $dirPart.'/' : '').$base.'.'.$ext;
        $i = 2;
        while ($disk->exists($path)) {
            $path = trim($this->option('folder'), '/').'/'.($dirPart ? $dirPart.'/' : '').$base.'-'.$i++.'.'.$ext;
        }
        $disk->put($path, $bytes);
        $name = $title ?: Str::of(pathinfo($rel, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->squish()->ucfirst()->toString();
        $file = MediaLibrary::register($path, $agencyId, 'import', $name);
        if ($file && $alt) {
            $file->update(['alt' => Str::limit($alt, 185, '')]);
        }
        $this->added++;
    }
}
