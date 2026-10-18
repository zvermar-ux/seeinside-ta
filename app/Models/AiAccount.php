<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AI keys, models and spending limits of one agency (agency_id NULL = super admin / platform) */
class AiAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['openai_key', 'anthropic_key', 'gemini_key'];

    protected function casts(): array
    {
        return [
            'openai_key' => 'encrypted',
            'anthropic_key' => 'encrypted',
            'gemini_key' => 'encrypted',
            'assistant_enabled' => 'boolean',
            'images_enabled' => 'boolean',
            'chat_enabled' => 'boolean',
            'platform_allowed' => 'boolean',
            'assistant_limit' => 'decimal:2',
            'user_daily_limit' => 'decimal:2',
            'chat_limit' => 'decimal:2',
            'platform_limit' => 'decimal:2',
            'prices' => 'array',
            'alerts_sent' => 'array',
            'chat' => 'array',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public static function for(?int $agencyId): self
    {
        return static::firstOrNew(['agency_id' => $agencyId]);
    }

    public static function platform(): self
    {
        return static::for(null);
    }

    public function key(string $provider): ?string
    {
        return match ($provider) {
            'openai' => $this->openai_key,
            'anthropic' => $this->anthropic_key,
            'gemini' => $this->gemini_key,
            default => null,
        } ?: null;
    }

    public function chatSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->chat ?? [], $key, $default);
    }
}
