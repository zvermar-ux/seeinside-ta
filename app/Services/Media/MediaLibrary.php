<?php

namespace App\Services\Media;

use App\Models\Accommodation;
use App\Models\AccommodationRoom;
use App\Models\Agency;
use App\Models\ChatQuestionnaire;
use App\Models\Event;
use App\Models\Group;
use App\Models\LibraryFile;
use App\Models\Media;
use App\Models\ObjectImage;
use App\Models\SliderImage;
use App\Models\Tour;
use App\Models\VisaType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Media library: registers uploaded images, finds where an image is used, indexes existing photos */
class MediaLibrary
{
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];

    public static function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXT, true);
    }

    /** Add (or update) a file of the public disk to the library */
    public static function register(string $path, ?int $agencyId, string $source = 'upload', ?string $name = null): ?LibraryFile
    {
        if (! $path || ! self::isImage($path)) {
            return null;
        }
        $disk = Storage::disk('public');
        try {
            if (! $disk->exists($path) || ! $disk->size($path)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        $existing = LibraryFile::where('path', $path)->first();
        if ($existing) {
            if ($existing->agency_id === null && $agencyId) {
                $existing->update(['agency_id' => $agencyId]);
            }

            return $existing;
        }
        $w = $h = null;
        try {
            if (! str_ends_with(strtolower($path), '.svg') && ($info = @getimagesize($disk->path($path)))) {
                [$w, $h] = $info;
            }
        } catch (Throwable) {
        }

        return LibraryFile::create([
            'agency_id' => $agencyId,
            'path' => $path,
            'name' => Str::limit($name ?: pathinfo($path, PATHINFO_FILENAME), 180, ''),
            'folder' => str_contains($path, '/') ? Str::before($path, '/') : null,
            'mime' => $disk->mimeType($path) ?: null,
            'size' => $disk->size($path),
            'width' => $w,
            'height' => $h,
            'source' => $source,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Every image the app already uses, with its agency: [path => agency_id] */
    public static function referenced(): array
    {
        $out = [];
        $add = function (?string $path, $agencyId) use (&$out) {
            if ($path && self::isImage($path) && ! str_starts_with($path, 'http')) {
                $out[$path] ??= $agencyId ? (int) $agencyId : null;
            }
        };
        foreach ([Tour::class, Event::class, Accommodation::class, VisaType::class] as $class) {
            $class::withoutGlobalScopes()->whereNotNull('cover_image')->get(['cover_image', 'agency_id'])->each(fn ($m) => $add($m->cover_image, $m->agency_id));
        }
        Media::query()->with('mediable')->get()->each(fn ($m) => $add($m->path, $m->mediable?->agency_id ?? ($m->mediable?->accommodation?->agency_id ?? null)));
        AccommodationRoom::query()->with('accommodation:id,agency_id')->whereNotNull('cover_image')->get()->each(fn ($r) => $add($r->cover_image, $r->accommodation?->agency_id));
        SliderImage::withoutGlobalScopes()->get(['path', 'agency_id'])->each(fn ($s) => $add($s->path, $s->agency_id));
        Group::withoutGlobalScopes()->whereNotNull('image')->get(['image', 'agency_id'])->each(fn ($g) => $add($g->image, $g->agency_id));
        VisaType::withoutGlobalScopes()->whereNotNull('info_cards')->get(['info_cards', 'agency_id'])->each(function ($v) use ($add) {
            foreach ((array) $v->info_cards as $c) {
                $add($c['image'] ?? null, $v->agency_id);
            }
        });
        ObjectImage::query()->with('travelObject:id,agency_id')->get()->each(fn ($i) => $add($i->path, $i->travelObject?->agency_id));
        Agency::query()->whereNotNull('logo')->get(['id', 'logo'])->each(fn ($a) => $add($a->logo, $a->id));
        ChatQuestionnaire::withoutGlobalScopes()->whereNotNull('image')->get(['image', 'agency_id'])->each(fn ($q) => $add($q->image, $q->agency_id));

        return $out;
    }

    /** Put every image already used in the app into the library. Returns the number of new entries. */
    public static function syncExisting(): int
    {
        @set_time_limit(0);
        $known = LibraryFile::pluck('path')->flip();
        $n = 0;
        foreach (self::referenced() as $path => $agencyId) {
            if (! isset($known[$path]) && self::register($path, $agencyId, 'scan')) {
                $n++;
            }
        }

        return $n;
    }

    /** Where the image is used (labels for the operator) */
    public static function usages(string $path): array
    {
        $out = [];
        $labels = ['tour' => __('Tour'), 'event' => __('Event'), 'accommodation' => __('Hotel'), 'visa_type' => __('Visa')];
        foreach ([Tour::class => 'title', Event::class => 'title', Accommodation::class => 'name', VisaType::class => 'name'] as $class => $field) {
            foreach ($class::withoutGlobalScopes()->where('cover_image', $path)->get() as $m) {
                $out[] = __('Main photo').': '.self::title($m, $field);
            }
        }
        foreach (Media::query()->where('path', $path)->with('mediable')->get() as $m) {
            $owner = $m->mediable;
            $out[] = __('Gallery').': '.($owner ? self::title($owner, isset($owner->title) ? 'title' : 'name') : '—');
        }
        foreach (AccommodationRoom::query()->where('cover_image', $path)->get() as $r) {
            $out[] = __('Room').': '.self::title($r, 'name');
        }
        foreach (SliderImage::withoutGlobalScopes()->where('path', $path)->get() as $s) {
            $out[] = __('Homepage slider').': '.($s->title ?: '#'.$s->id);
        }
        foreach (Group::withoutGlobalScopes()->where('image', $path)->get() as $g) {
            $out[] = __('Groups & destinations').': '.$g->display_name;
        }
        foreach (VisaType::withoutGlobalScopes()->where('info_cards', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $path).'%')->get() as $v) {
            $out[] = __('Info cards').': '.self::title($v, 'name');
        }
        foreach (Agency::query()->where('logo', $path)->get() as $a) {
            $out[] = __('Logo').': '.$a->name;
        }

        return array_values(array_unique($out));
    }

    protected static function title($m, string $field): string
    {
        $v = $m->{$field} ?? null;

        return is_array($v) ? (string) ($v['mk'] ?? reset($v)) : (string) $v;
    }

    /** Agency a new upload belongs to: the edited record's agency, the form's agency field, or the user's agency */
    public static function agencyFor($component = null): ?int
    {
        try {
            $record = $component?->getRecord();
            if ($record && $record->getAttribute('agency_id')) {
                return (int) $record->getAttribute('agency_id');
            }
            $lw = $component?->getLivewire();
            if ($lw && method_exists($lw, 'getOwnerRecord') && ($owner = $lw->getOwnerRecord())) {
                $aid = $owner->getAttribute('agency_id') ?? $owner->accommodation?->agency_id ?? null;
                if ($aid) {
                    return (int) $aid;
                }
            }
            $data = $lw->data ?? null;
            if (is_array($data) && ! empty($data['agency_id'])) {
                return (int) $data['agency_id'];
            }
        } catch (Throwable) {
        }

        return Auth::user()?->agency_id ? (int) Auth::user()->agency_id : null;
    }
}
