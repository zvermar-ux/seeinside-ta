<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourExtra extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected $touches = ['tour'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'is_optional' => 'boolean', 'price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (TourExtra $e) {
            $e->is_optional = $e->is_optional ?? true;
            $e->price = (float) $e->price;
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
