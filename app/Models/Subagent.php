<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Subagent extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'has_access' => 'boolean',
            'no_balance_limit' => 'boolean',
            'commission_percent' => 'decimal:2',
        ];
    }
}
