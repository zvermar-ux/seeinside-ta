<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Photo gallery (shared "media" table) with one main image copied to cover_image */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderByDesc('is_main')->orderBy('sort_order');
    }

    public function refreshCover(): void
    {
        $this->forceFill(['cover_image' => $this->media()->value('path')])->saveQuietly();
    }
}
