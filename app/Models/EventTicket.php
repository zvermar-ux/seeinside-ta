<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTicket extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'is_active' => 'boolean', 'price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (EventTicket $t) {
            $t->is_active = $t->is_active ?? true;
            $t->sold = (int) $t->sold;
        });
        static::saved(fn (EventTicket $t) => $t->event?->save());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function available(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->sold);
    }
}
