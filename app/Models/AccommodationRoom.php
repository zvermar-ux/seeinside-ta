<?php

namespace App\Models;

use App\Enums\Board;
use App\Enums\RoomType;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationRoom extends Model
{
    use HasMedia, Localized;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'room_amenities' => 'array',
            'room_type' => RoomType::class,
            'board' => Board::class,
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AccommodationRoom $r) {
            $r->is_active = $r->is_active ?? true;
            $r->max_children = (int) $r->max_children;
        });
        static::saved(fn (AccommodationRoom $r) => $r->accommodation?->save());
        static::deleted(fn (AccommodationRoom $r) => $r->accommodation?->save());
    }

    /** Rooms have no own cover column; their first photo is used directly */
    public function refreshCover(): void {}

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class)->orderBy('date_from');
    }
}
