<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Charter extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'transport_only_price' => 'decimal:2',
        ];
    }
}
