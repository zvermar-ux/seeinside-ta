<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Record type defined by the super admin (e.g. Transport, Transfers, Rent-a-car) with its own fields */
class RecordType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'singular' => 'array', 'fields' => 'array', 'bookable' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (RecordType $t) {
            $t->slug = Str::slug($t->slug ?: ($t->name['en'] ?? null) ?: ($t->name['mk'] ?? 'type'), '_', 'mk') ?: 'type_'.Str::lower(Str::random(4));
            // field keys: safe, unique
            $seen = [];
            $t->fields = array_values(array_map(function ($f) use (&$seen) {
                $k = Str::slug((string) ($f['key'] ?? '') ?: (string) ($f['label']['en'] ?? $f['label']['mk'] ?? 'field'), '_', 'mk') ?: 'field';
                $base = $k;
                $i = 2;
                while (isset($seen[$k])) {
                    $k = $base.'_'.$i++;
                }
                $seen[$k] = true;
                $f['key'] = $k;

                return $f;
            }, array_filter((array) $t->fields, 'is_array')));
        });
    }

    /** Field types the super admin can choose */
    public static function fieldTypes(): array
    {
        return [
            'text' => __('Short text'), 'textarea' => __('Long text'), 'richtext' => __('Formatted text'),
            'number' => __('Number'), 'price' => __('Price'), 'date' => __('Date'), 'datetime' => __('Date & time'), 'time' => __('Time'),
            'select' => __('Choice (one)'), 'multiselect' => __('Choice (several)'), 'toggle' => __('Yes / no'),
            'image' => __('Image'), 'gallery' => __('Gallery'), 'file' => __('File (PDF…)'),
            'url' => __('Link'), 'email' => __('E-mail'), 'phone' => __('Phone'), 'location' => __('Location (map)'), 'list' => __('List (one item per line)'),
        ];
    }

    public static function icons(): array
    {
        return [
            'heroicon-o-truck' => __('Truck / transport'), 'heroicon-o-paper-airplane' => __('Plane'), 'heroicon-o-map' => __('Map'),
            'heroicon-o-ticket' => __('Ticket'), 'heroicon-o-building-office-2' => __('Building'), 'heroicon-o-home-modern' => __('House'),
            'heroicon-o-key' => __('Key / rent'), 'heroicon-o-lifebuoy' => __('Cruise / sea'), 'heroicon-o-sun' => __('Sun'),
            'heroicon-o-academic-cap' => __('Education'), 'heroicon-o-heart' => __('Health / wellness'), 'heroicon-o-gift' => __('Gift'),
            'heroicon-o-shield-check' => __('Insurance'), 'heroicon-o-camera' => __('Camera'), 'heroicon-o-squares-2x2' => __('Other'),
        ];
    }

    public function label(?string $locale = null): string
    {
        $l = $locale ?? app()->getLocale();

        return (string) (($this->name[$l] ?? null) ?: ($this->name['mk'] ?? $this->slug));
    }

    public function singularLabel(?string $locale = null): string
    {
        $l = $locale ?? app()->getLocale();

        return (string) (($this->singular[$l] ?? null) ?: ($this->singular['mk'] ?? null) ?: $this->label($l));
    }

    public function fieldDefs(): array
    {
        return array_values(array_filter((array) $this->fields, fn ($f) => is_array($f) && filled($f['key'] ?? null)));
    }

    public function records(): HasMany
    {
        return $this->hasMany(CustomRecord::class);
    }
}
