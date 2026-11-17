<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hotel described by a booking system (Hotelbeds…). Prices are never stored – they are asked live. */
class SupplierHotel extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'facilities' => 'array',
            'rooms' => 'array',
            'boards' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'markup_percent' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class, 'supplier_connection_id');
    }

    /** Website id: "{connection}-{hotel code}", e.g. "3-12345" */
    public function publicId(): string
    {
        return $this->supplier_connection_id.'-'.$this->code;
    }

    public function coverUrl(): ?string
    {
        return $this->images[0]['url'] ?? null;
    }
}
