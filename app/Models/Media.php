<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'caption' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(function (Media $media) {
            if ($media->is_main && ($media->wasRecentlyCreated || $media->wasChanged('is_main'))) {
                static::where('mediable_type', $media->mediable_type)->where('mediable_id', $media->mediable_id)
                    ->whereKeyNot($media->id)->update(['is_main' => false]);
            }
            $media->mediable?->refreshCover();
            $media->mediable?->touch();   // the website re-syncs the gallery
        });

        static::deleted(function (Media $media) {
            $owner = $media->mediable;
            $usedAsCover = $owner && $owner->getAttribute('cover_image') === $media->path;
            if ($media->path && ! $usedAsCover && ! static::where('path', $media->path)->exists()) {
                Storage::disk('public')->delete($media->path);
            }
            $owner?->refreshCover();
            $owner?->touch();
        });
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
