<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A website (WordPress) that reads the catalog through the API and receives webhooks */
class ApiClient extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected $hidden = ['key_hash', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // placeholder until issueKey() is called
        static::creating(function (ApiClient $c) {
            $c->key_prefix ??= Str::lower(Str::random(8));
            $c->key_hash ??= hash('sha256', Str::random(64));
            $c->webhook_secret ??= Str::random(48);
        });
    }

    /** Creates a new key; the plain key is returned once and only its hash is stored */
    public function issueKey(): string
    {
        $prefix = Str::lower(Str::random(8));
        $plain = 'ta_'.$prefix.'_'.Str::random(40);
        $this->forceFill([
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'webhook_secret' => $this->webhook_secret ?: Str::random(48),
        ])->save();

        return $plain;
    }

    public static function findByKey(?string $plain): ?self
    {
        if (! $plain || ! preg_match('/^ta_([a-z0-9]{8})_/', $plain, $m)) {
            return null;
        }

        $client = static::withoutGlobalScopes()->where('key_prefix', $m[1])->where('is_active', true)->first();

        return $client && hash_equals($client->key_hash, hash('sha256', $plain)) ? $client : null;
    }
}
