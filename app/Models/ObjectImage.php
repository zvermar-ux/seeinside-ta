<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ObjectImage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (ObjectImage $image) {
            if ($image->is_main && ($image->wasRecentlyCreated || $image->wasChanged('is_main'))) {
                static::where('travel_object_id', $image->travel_object_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_main' => false]);
            }
            $image->travelObject?->refreshCover();
        });

        static::deleted(function (ObjectImage $image) {
            if ($image->path && ! static::where('path', $image->path)->exists() && ! LibraryFile::where('path', $image->path)->exists()) {
                Storage::disk('public')->delete($image->path);
            }
            $image->travelObject?->refreshCover();
        });
    }

    public function travelObject(): BelongsTo
    {
        return $this->belongsTo(TravelObject::class);
    }
}
