<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDay extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected $touches = ['tour'];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
