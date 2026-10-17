<?php

namespace App\Models;

use App\Enums\GroupType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Group extends Model
{
    protected static function booted(): void
    {
        static::saved(fn ($m) => \App\Services\Website\WebsiteNotifier::metaChanged($m->agency_id));
        static::deleted(fn ($m) => \App\Services\Website\WebsiteNotifier::metaChanged($m->agency_id));
    }

    use BelongsToAgency, HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'type' => GroupType::class,
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'show_in_offer' => 'boolean',
            'use_external_url' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Objects assigned to this record as "group" */
    public function travelObjects(): HasMany
    {
        return $this->hasMany(TravelObject::class, 'group_id');
    }

    /** Objects assigned to this record as "destination" */
    public function destinationObjects(): HasMany
    {
        return $this->hasMany(TravelObject::class, 'destination_id');
    }

    public function scopeOfType(Builder $query, GroupType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** Name in current locale with fallback to any filled locale */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->getTranslation('name', app()->getLocale(), false);
        if (blank($name)) {
            $name = collect($this->getTranslations('name'))->first(fn ($v) => filled($v)) ?? '';
        }

        return (string) $name;
    }
}
