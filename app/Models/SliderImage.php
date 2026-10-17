<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SliderImage extends Model
{
    use BelongsToAgency, HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['supertitle', 'title', 'subtitle', 'button_label', 'button2_label'];

    /** Slider changes are pushed to the website as "slider.updated" / "slider.deleted" webhooks */
    public static function websiteType(): string
    {
        return 'slider';
    }

    protected static function booted(): void
    {
        static::saved(fn (SliderImage $s) => \App\Services\Website\WebsiteNotifier::changed($s));
        static::deleted(fn (SliderImage $s) => \App\Services\Website\WebsiteNotifier::deleted($s));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'open_in_new_tab' => 'boolean'];
    }
}
