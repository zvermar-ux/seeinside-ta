<?php

namespace App\Models;

use App\Enums\ObjectType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TravelObject extends Model
{
    use BelongsToAgency, HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['description', 'service_description'];

    protected function casts(): array
    {
        return [
            'type' => ObjectType::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'stars' => 'integer',
            'min_payment_percent' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'destination_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ObjectImage::class)->orderByDesc('is_main')->orderBy('sort_order');
    }

    /** Refresh cover_image from the main (or first) image */
    public function refreshCover(): void
    {
        $this->cover_image = $this->images()->value('path');
        $this->saveQuietly();
    }

    /** Coordinates as [lat, lng] when map_location is "lat,lng" */
    public function coordinates(): ?array
    {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', (string) $this->map_location, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        return null;
    }
}
