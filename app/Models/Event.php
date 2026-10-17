<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPromotions;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Localized;
use App\Models\Concerns\SyncsToWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToAgency, HasMedia, HasPromotions, HasSlug, Localized, SyncsToWebsite;

    protected $guarded = ['id'];

    public const TRANSLATABLE = ['title', 'short_description', 'description', 'included', 'not_included', 'notes', 'seo_title', 'seo_description'];

    public static function websiteType(): string
    {
        return 'event';
    }

    protected function casts(): array
    {
        return array_fill_keys(self::TRANSLATABLE, 'array') + [
            'category' => EventCategory::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'video_urls' => 'array',
            'booking_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'price_from' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $e) {
            if ($e->exists) {
                $min = $e->tickets()->where('is_active', true)->min('price');
                if ($min !== null) {
                    $e->price_from = $e->promoPrice((float) $min);
                }
            }
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'destination_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EventTicket::class)->orderBy('sort_order');
    }
}
