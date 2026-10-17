<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** Generates a unique (per agency) URL slug from the title/name */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            if (filled($model->slug)) {
                $model->slug = Str::slug($model->slug);
            } else {
                $source = $model->slugSource();
                $model->slug = Str::slug($source) ?: Str::lower(Str::random(8));
            }

            $agencyId = $model->agency_id ?? \Illuminate\Support\Facades\Auth::user()?->agency_id;
            $base = $model->slug;
            $i = 2;
            while (static::withoutGlobalScopes()
                ->where('agency_id', $agencyId)
                ->where('slug', $model->slug)
                ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
                ->exists()) {
                $model->slug = $base.'-'.$i++;
            }
        });
    }

    protected function slugSource(): string
    {
        foreach (['title', 'name'] as $attr) {
            $v = $this->getAttribute($attr);
            if (is_array($v)) {
                $v = $v['mk'] ?? $v['en'] ?? reset($v);
            }
            if (filled($v)) {
                return (string) $v;
            }
        }

        return '';
    }
}
