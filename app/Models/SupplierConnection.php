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
}
