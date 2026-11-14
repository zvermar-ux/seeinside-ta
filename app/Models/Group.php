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

    public function placeTours(): HasMany
    {
        return $this->hasMany(Tour::class, 'place_id');
    }

    public function placeAccommodations(): HasMany
    {
        return $this->hasMany(Accommodation::class, 'place_id');
    }

    /** Parents up to the top: [direct parent, …, root] (stops on loops) */
    public function ancestors(): array
    {
        $out = [];
        $seen = [$this->id => true];
        $g = $this;
        while ($g->parent_id && ! isset($seen[$g->parent_id]) && ($p = $g->parent()->withoutGlobalScopes()->first())) {
            $out[] = $p;
            $seen[$p->id] = true;
            $g = $p;
        }

        return $out;
    }

    /** Place with its parent places: "Никиана, Лефкада" (destinations are not repeated) */
    public function placePath(string $sep = ', ', ?string $locale = null): string
    {
        $parts = [$this->nameIn($locale)];
        foreach ($this->ancestors() as $a) {
            if ($a->type !== GroupType::Place) {
                break;
            }
            $parts[] = $a->nameIn($locale);
        }

        return implode($sep, $parts);
    }

    /** The destination a place belongs to (the first destination among its parents) */
    public function destinationOfPlace(): ?self
    {
        foreach ($this->ancestors() as $a) {
            if ($a->type === GroupType::Destination) {
                return $a;
            }
        }

        return null;
    }

    public function nameIn(?string $locale = null): string
    {
        return $locale ? ((string) $this->getTranslation('name', $locale, false) ?: $this->display_name) : $this->display_name;
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
