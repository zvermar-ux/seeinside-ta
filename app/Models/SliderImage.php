<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SliderImage extends Model
{
    use BelongsToAgency, HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['title'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
