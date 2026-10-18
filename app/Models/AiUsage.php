<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'via_platform' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public static function featureLabel(string $f): string
    {
        return match ($f) {
            'assistant' => __('Operator assistant'),
            'image' => __('AI images'),
            'chat' => __('Live chat bot'),
            'training' => __('Bot training'),
            default => $f,
        };
    }
}
