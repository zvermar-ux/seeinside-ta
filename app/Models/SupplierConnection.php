<?php

namespace App\Models;

use App\Enums\SupplierProvider;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class SupplierConnection extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'provider' => SupplierProvider::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    /** The website learns at once that hotels are on/off or the destinations changed */
    protected static function booted(): void
    {
        $changed = function ($m) {
            \Illuminate\Support\Facades\Cache::forget('hotels:dest:'.$m->agency_id);
            \Illuminate\Support\Facades\Cache::forget('hotels:index:'.$m->agency_id);
            \App\Services\Website\WebsiteNotifier::metaChanged($m->agency_id);
        };
        static::saved(fn ($m) => ($m->wasRecentlyCreated || $m->wasChanged(['is_active', 'provider', 'agency_id'])) && $changed($m));
        static::deleted($changed);
    }
}
