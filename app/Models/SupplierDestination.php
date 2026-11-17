<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDestination extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['zones' => 'array', 'is_active' => 'boolean'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class, 'supplier_connection_id');
    }

    /** The website learns at once that hotels are on/off or the destinations changed */
    protected static function booted(): void
    {
        $changed = function ($m) {
            \Illuminate\Support\Facades\Cache::forget('hotels:dest:'.$m->agency_id);
            \App\Services\Website\WebsiteNotifier::metaChanged($m->agency_id);
        };
        static::saved(fn ($m) => ($m->wasRecentlyCreated || $m->wasChanged(['is_active', 'name', 'hotels_count'])) && $changed($m));
        static::deleted($changed);
    }
}
