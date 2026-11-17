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
}
